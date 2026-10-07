<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Evaluation;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Reject reason codes, with customer-facing wording (getLabel) and admin wording (option source).
 */
class RejectReason implements OptionSourceInterface
{
    public const NOT_VERIFIED = 'not_verified';
    public const NOT_LOWER = 'not_lower';
    public const EXCEEDS_CAP = 'exceeds_cap';
    public const BELOW_FLOOR = 'below_floor';
    public const PRODUCT_UNAVAILABLE = 'product_unavailable';
    public const NOT_ELIGIBLE = 'not_eligible';
    public const REJECTED_BY_ADMIN = 'rejected_by_admin';

    /**
     * Reasons an admin may pick when rejecting by hand. Each one maps to a customer-facing email line.
     */
    public const ADMIN_CHOICES = [
        self::REJECTED_BY_ADMIN,
        self::NOT_VERIFIED,
        self::NOT_LOWER,
        self::EXCEEDS_CAP,
        self::BELOW_FLOOR,
        self::NOT_ELIGIBLE,
    ];

    /**
     * Customer-facing wording; deliberately does not reveal cost or cap values.
     *
     * @param string|null $code
     * @return string
     */
    public function getLabel(?string $code): string
    {
        $labels = [
            self::NOT_VERIFIED => __('We could not verify the competitor price.'),
            self::NOT_LOWER => __('Our price is already the same or lower.'),
            self::EXCEEDS_CAP => __('The price difference is larger than we can match.'),
            self::BELOW_FLOOR => __('We are unable to match this price.'),
            self::PRODUCT_UNAVAILABLE => __('The product is no longer available.'),
            self::NOT_ELIGIBLE => __('This product is not eligible for price matching.'),
            self::REJECTED_BY_ADMIN => __('Your request was reviewed and could not be approved.'),
        ];
        return (string)($labels[$code] ?? __('Your request could not be approved.'));
    }

    /**
     * Admin wording, used by the request grid and detail page.
     *
     * @return array<string, \Magento\Framework\Phrase>
     */
    public function getAdminLabels(): array
    {
        return [
            self::NOT_VERIFIED => __('Price not verified'),
            self::NOT_LOWER => __('Our price is not higher'),
            self::EXCEEDS_CAP => __('Exceeds discount cap'),
            self::BELOW_FLOOR => __('Below price floor'),
            self::PRODUCT_UNAVAILABLE => __('Product unavailable'),
            self::NOT_ELIGIBLE => __('Product not eligible'),
            self::REJECTED_BY_ADMIN => __('Rejected by admin'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->getAdminLabels() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        return $options;
    }
}
