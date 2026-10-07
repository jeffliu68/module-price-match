<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Resolver;

use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface;
use MagentoGuy\PriceMatch\Model\Evaluation\RejectReason;
use MagentoGuy\PriceMatch\Model\Status;

/**
 * Customer-facing projection shared by GraphQL and the Luma JSON endpoints. Never exposes internal
 * fields (verified price, errors, attempts, reviewer).
 */
class RequestFormatter
{
    /**
     * @param Status $status
     * @param RejectReason $rejectReason
     */
    public function __construct(
        private readonly Status $status,
        private readonly RejectReason $rejectReason
    ) {
    }

    /**
     * @param PriceMatchRequestInterface $request
     * @return array
     */
    public function format(PriceMatchRequestInterface $request): array
    {
        $currency = $request->getCurrencyCode();
        $money = static fn (?float $value) => $value === null ? null : ['value' => $value, 'currency' => $currency];
        $status = $request->getStatus();
        $approved = $status === Status::APPROVED;

        return [
            'id' => (int)$request->getRequestId(),
            'sku' => $request->getSku(),
            'competitor_url' => $request->getCompetitorUrl(),
            'competitor_price' => $money($request->getClaimedPrice()),
            'status' => strtoupper($status),
            'status_label' => (string)($this->status->getLabels()[$status] ?? $status),
            'reject_reason' => $status === Status::REJECTED
                ? $this->rejectReason->getLabel($request->getRejectReason())
                : null,
            'matched_price' => $money($request->getMatchedPrice()),
            'discount' => $money($request->getApprovedDiscount()),
            'coupon_code' => $approved ? $request->getCouponCode() : null,
            'coupon_expires_at' => $approved ? $request->getExpiresAt() : null,
            'created_at' => (string)$request->getCreatedAt(),
            'updated_at' => $request->getUpdatedAt(),
        ];
    }
}
