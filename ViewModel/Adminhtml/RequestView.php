<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\ViewModel\Adminhtml;

use Magento\Backend\Model\UrlInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface;
use MagentoGuy\PriceMatch\Api\PriceMatchRequestRepositoryInterface;
use MagentoGuy\PriceMatch\Model\Evaluation\RejectReason;
use MagentoGuy\PriceMatch\Model\ResourceModel\History;
use MagentoGuy\PriceMatch\Model\Status;

/**
 * Admin request detail page. Values are returned raw; the template escapes everything.
 */
class RequestView implements ArgumentInterface
{
    /**
     * @var PriceMatchRequestInterface|null
     */
    private ?PriceMatchRequestInterface $request = null;

    /**
     * @param RequestInterface $httpRequest
     * @param PriceMatchRequestRepositoryInterface $repository
     * @param History $history
     * @param CustomerRepositoryInterface $customerRepository
     * @param Status $status
     * @param RejectReason $rejectReason
     * @param PriceCurrencyInterface $priceCurrency
     * @param TimezoneInterface $timezone
     * @param UrlInterface $urlBuilder
     * @param AuthorizationInterface $authorization
     */
    public function __construct(
        private readonly RequestInterface $httpRequest,
        private readonly PriceMatchRequestRepositoryInterface $repository,
        private readonly History $history,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly Status $status,
        private readonly RejectReason $rejectReason,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly TimezoneInterface $timezone,
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorizationInterface $authorization
    ) {
    }

    /**
     * @return PriceMatchRequestInterface
     * @throws NoSuchEntityException
     */
    public function getRequest(): PriceMatchRequestInterface
    {
        if ($this->request === null) {
            $this->request = $this->repository->getById((int)$this->httpRequest->getParam('id'));
        }
        return $this->request;
    }

    /**
     * @return string
     */
    public function getCustomerLabel(): string
    {
        $customerId = $this->getRequest()->getCustomerId();
        try {
            $customer = $this->customerRepository->getById($customerId);
            return sprintf('%s %s <%s>', $customer->getFirstname(), $customer->getLastname(), $customer->getEmail());
        } catch (NoSuchEntityException $e) {
            return (string)__('Customer #%1 (deleted)', $customerId);
        }
    }

    /**
     * @return string
     */
    public function getCustomerUrl(): string
    {
        return $this->urlBuilder->getUrl('customer/index/edit', ['id' => $this->getRequest()->getCustomerId()]);
    }

    /**
     * Only http(s) URLs are ever stored (CompetitorUrlPolicy); re-checked here before rendering a link.
     *
     * @return bool
     */
    public function isCompetitorUrlLinkable(): bool
    {
        $scheme = strtolower((string)parse_url($this->getRequest()->getCompetitorUrl(), PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * @param string|null $status
     * @return string
     */
    public function getStatusLabel(?string $status): string
    {
        return $status === null ? '' : (string)($this->status->getLabels()[$status] ?? $status);
    }

    /**
     * @return string
     */
    public function getRejectReasonLabel(): string
    {
        $code = $this->getRequest()->getRejectReason();
        return $code === null ? '' : (string)($this->rejectReason->getAdminLabels()[$code] ?? $code);
    }

    /**
     * @return string What the customer was told
     */
    public function getCustomerRejectMessage(): string
    {
        return $this->rejectReason->getLabel($this->getRequest()->getRejectReason());
    }

    /**
     * @param float|null $amount
     * @return string
     */
    public function formatMoney(?float $amount): string
    {
        return $amount === null
            ? '—'
            : $this->priceCurrency->format($amount, false, 2, null, $this->getRequest()->getCurrencyCode());
    }

    /**
     * Discount as a share of our price at evaluation time.
     *
     * @return string
     */
    public function getDiscountPercent(): string
    {
        $request = $this->getRequest();
        $discount = $request->getApprovedDiscount();
        $current = $request->getCurrentPrice();
        return $discount === null || !$current ? '' : sprintf('%.2f%%', $discount / $current * 100);
    }

    /**
     * Claimed gap as a share of our price, which is what the automatic cap compares against.
     *
     * @return string
     */
    public function getClaimedGapPercent(): string
    {
        $request = $this->getRequest();
        $current = $request->getCurrentPrice();
        return !$current ? '' : sprintf('%.2f%%', ($current - $request->getClaimedPrice()) / $current * 100);
    }

    /**
     * @param string|null $utc
     * @return string Admin locale and timezone
     */
    public function formatDate(?string $utc): string
    {
        return $utc
            ? $this->timezone->formatDateTime($utc, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::MEDIUM)
            : '—';
    }

    /**
     * @return array[] Each: date, event, status, actor, note
     */
    public function getHistory(): array
    {
        $request = $this->getRequest();
        $rows = [[
            'date' => $this->formatDate($request->getCreatedAt()),
            'event' => (string)__('Submitted'),
            'status' => $this->getStatusLabel(Status::PENDING),
            'actor' => (string)__('Customer'),
            'note' => '',
        ]];
        $events = $this->getEventLabels();
        foreach ($this->history->getByRequestId((int)$request->getRequestId()) as $row) {
            $rows[] = [
                'date' => $this->formatDate($row['created_at']),
                'event' => (string)($events[$row['event']] ?? $row['event']),
                'status' => $this->getStatusLabel($row['status']),
                'actor' => $row['actor'] ?: (string)__('System'),
                'note' => (string)$row['note'],
            ];
        }
        return $rows;
    }

    /**
     * @return bool
     */
    public function canReview(): bool
    {
        return in_array($this->getRequest()->getStatus(), [Status::PENDING, Status::NEEDS_REVIEW], true)
            && $this->authorization->isAllowed('MagentoGuy_PriceMatch::review');
    }

    /**
     * @return array<string, string>
     */
    public function getRejectReasonOptions(): array
    {
        $labels = $this->rejectReason->getAdminLabels();
        $options = [];
        foreach (RejectReason::ADMIN_CHOICES as $code) {
            $options[$code] = (string)$labels[$code];
        }
        return $options;
    }

    /**
     * @param string $action approve|reject
     * @return string
     */
    public function getActionUrl(string $action): string
    {
        return $this->urlBuilder->getUrl(
            'pricematch/request/' . $action,
            ['id' => $this->getRequest()->getRequestId(), 'back' => 'view']
        );
    }

    /**
     * @return string
     */
    public function getOrderUrl(): string
    {
        $orderId = $this->getRequest()->getOrderId();
        return $orderId ? $this->urlBuilder->getUrl('sales/order/view', ['order_id' => $orderId]) : '';
    }

    /**
     * @return string
     */
    public function getBackUrl(): string
    {
        return $this->urlBuilder->getUrl('pricematch/request/index');
    }

    /**
     * @return array<string, \Magento\Framework\Phrase>
     */
    private function getEventLabels(): array
    {
        return [
            History::EVENT_APPROVED => __('Approved, coupon issued'),
            History::EVENT_REJECTED => __('Rejected'),
            History::EVENT_NEEDS_REVIEW => __('Sent to manual review'),
            History::EVENT_RETRY_SCHEDULED => __('Retry scheduled'),
            History::EVENT_RESERVED => __('Coupon reserved at checkout'),
            History::EVENT_RELEASED => __('Reservation released'),
            History::EVENT_ORDER_ATTACHED => __('Order placed'),
            History::EVENT_EXPIRED => __('Coupon expired'),
        ];
    }
}
