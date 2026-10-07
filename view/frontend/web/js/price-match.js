/**
 * Copyright © MagentoGuy. All rights reserved.
 *
 * PDP "Price match" modal. Submits once (idempotency key survives retries of the same attempt),
 * then polls the request status briefly so the shopper can see the async result land.
 */
define([
    'jquery',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'Magento_Ui/js/modal/modal',
    'mage/cookies'
], function ($, $t, customerData) {
    'use strict';

    var POLL_INTERVAL_MS = 2000,
        POLL_MAX = 20;

    function newIdempotencyKey() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return 'pm-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
    }

    // Server dates are UTC "Y-m-d H:i:s"; show them in the shopper's own timezone, labelled.
    function formatUtcDate(utc) {
        var date = new Date(String(utc).replace(' ', 'T') + 'Z');

        if (isNaN(date.getTime())) {
            return utc + ' UTC';
        }
        try {
            // Explicit fields: ECMA-402 throws when dateStyle/timeStyle are combined with timeZoneName.
            return new Intl.DateTimeFormat(undefined, {
                year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', timeZoneName: 'short'
            })
                .format(date);
        } catch (e) {
            return date.toString();
        }
    }

    function formatMoney(money) {
        if (!money) {
            return '';
        }
        try {
            return new Intl.NumberFormat(undefined, {style: 'currency', currency: money.currency}).format(money.value);
        } catch (e) {
            return money.value.toFixed(2) + ' ' + money.currency;
        }
    }

    return function (config, element) {
        var $root = $(element),
            $modal = $root.find('[data-role=pricematch-modal]'),
            $form = $root.find('[data-role=pricematch-form]'),
            $message = $root.find('[data-role=pricematch-message]'),
            idempotencyKey = null,
            $submitButton = $(),
            busy = false,
            pollTimer = null;

        var MESSAGE_TYPES = 'error success notice info';

        function showMessage(type, text) {
            $message.removeClass('no-display ' + MESSAGE_TYPES)
                .addClass(type)
                .empty()
                .append($('<div></div>').text(text));
        }

        // Drop the type class too: Luma's `.message.error { display: block }` outranks `.no-display`,
        // so a leftover type class would show an empty coloured box on the next open.
        function hideMessage() {
            $message.removeClass(MESSAGE_TYPES).addClass('no-display').empty();
        }

        function showResult(request) {
            var lines = [];

            switch (request.status) {
                case 'APPROVED':
                    lines.push($t('Approved! Use code %1 at checkout.').replace('%1', request.coupon_code));
                    lines.push($t('Up to %1 off one unit, valid until %2. It works only for your account and this product.')
                        .replace('%1', formatMoney(request.discount))
                        .replace('%2', formatUtcDate(request.coupon_expires_at)));
                    showMessage('success', lines.join(' '));
                    break;
                case 'REJECTED':
                    showMessage('error', request.reject_reason || $t('Your request could not be approved.'));
                    break;
                case 'NEEDS_REVIEW':
                    showMessage('notice', $t('We need a closer look at this one. We will email you once it has been reviewed.'));
                    break;
                default:
                    showMessage('info', $t('Request #%1 received. We are verifying the price…').replace('%1', request.id));
            }
        }

        function stopPolling() {
            if (pollTimer) {
                window.clearTimeout(pollTimer);
                pollTimer = null;
            }
        }

        function poll(requestId, remaining) {
            if (remaining <= 0) {
                showMessage('info', $t('Still checking. We will email you, and you can follow it under My Account > Price Match Requests.'));
                return;
            }
            pollTimer = window.setTimeout(function () {
                $.ajax({url: config.statusUrl, data: {id: requestId}, dataType: 'json', cache: false})
                    .done(function (response) {
                        if (!response.success) {
                            return;
                        }
                        showResult(response.request);
                        if (response.request.status === 'PENDING') {
                            poll(requestId, remaining - 1);
                        }
                    })
                    .fail(function () {
                        poll(requestId, remaining - 1);
                    });
            }, POLL_INTERVAL_MS);
        }

        // Both the dropdown (configurable.js) and swatch (swatch-renderer.js) widgets keep their choices in
        // super_attribute[id] inputs on the add-to-cart form; only the dropdown widget fills selected_configurable_option.
        function selectedOptions() {
            var options = {};

            $('#product_addtocart_form').find('[name^="super_attribute["]').each(function () {
                options[this.name] = $(this).val() || '';
            });
            return options;
        }

        function optionsComplete(options) {
            var names = Object.keys(options);

            return names.length > 0 && names.every(function (name) {
                return options[name] !== '';
            });
        }

        function submit() {
            var url = $.trim($form.find('[name=competitor_url]').val()),
                rawPrice = $.trim($form.find('[name=competitor_price]').val()),
                price = Math.round(parseFloat(rawPrice) * 100) / 100;

            if (busy) {
                return;
            }
            if (!url && !rawPrice) {
                showMessage('error', $t('Please enter the competitor URL and price.'));
                return;
            }
            if (!url) {
                showMessage('error', $t('Please enter the competitor product URL.'));
                return;
            }
            if (!rawPrice) {
                showMessage('error', $t('Please enter the competitor price.'));
                return;
            }
            if (!(price > 0)) {
                showMessage('error', $t('Please enter a competitor price greater than zero.'));
                return;
            }
            if (config.isConfigurable && !optionsComplete(selectedOptions())) {
                showMessage('error', $t('Please select the product options first.'));
                return;
            }

            busy = true;
            showMessage('info', $t('Submitting…'));
            $.ajax({
                url: config.submitUrl,
                type: 'POST',
                dataType: 'json',
                data: $.extend({
                    form_key: $.mage.cookies.get('form_key'),
                    product_id: config.productId,
                    child_id: $('input[name="selected_configurable_option"]').val() || '',
                    competitor_url: url,
                    competitor_price: price.toFixed(2),
                    idempotency_key: idempotencyKey
                }, config.isConfigurable ? selectedOptions() : {})
            }).done(function (response) {
                showResult(response.request);
                $form.addClass('no-display');
                $submitButton.hide();
                $modal.modal('setTitle', $t('Price match request #%1').replace('%1', response.request.id));
                if (response.request.status === 'PENDING') {
                    poll(response.request.id, POLL_MAX);
                }
            }).fail(function (xhr) {
                var response = xhr.responseJSON || {};

                if (xhr.status === 401) {
                    window.location.href = config.loginUrl;
                    return;
                }
                showMessage('error', response.message || $t('Something went wrong. Please try again.'));
                // Validation errors get a fresh key so a corrected submission is a new request.
                if (xhr.status === 422) {
                    idempotencyKey = newIdempotencyKey();
                }
            }).always(function () {
                busy = false;
            });
        }

        $modal.modal({
            type: 'popup',
            title: $t('Price match'),
            buttons: [{
                text: $t('Request price match'),
                'class': 'action primary',
                click: submit
            }],
            closed: stopPolling
        });

        $submitButton = $modal.closest('.modal-popup').find('.modal-footer .action.primary');

        $form.on('change', '[name=competitor_price]', function () {
            var value = parseFloat(this.value);

            if (value > 0) {
                this.value = (Math.round(value * 100) / 100).toFixed(2);
            }
        });

        $root.on('click', '[data-role=pricematch-open]', function () {
            var customer = customerData.get('customer')();

            if (!customer || !customer.firstname) {
                window.location.href = config.loginUrl;
                return;
            }
            stopPolling();
            idempotencyKey = newIdempotencyKey();
            $form.removeClass('no-display').trigger('reset');
            hideMessage();
            $modal.modal('setTitle', $t('Price match'));
            $submitButton.show();
            $modal.removeClass('no-display').modal('openModal');
        });
    };
});
