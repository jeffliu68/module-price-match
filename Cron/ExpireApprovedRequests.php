<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Cron;

use Magento\Framework\Stdlib\DateTime\DateTime;
use MagentoGuy\PriceMatch\Api\PriceMatchRequestRepositoryInterface;
use MagentoGuy\PriceMatch\Model\Decision\RequestDecider;
use MagentoGuy\PriceMatch\Model\ResourceModel\PriceMatchRequest as RequestResource;

/**
 * Housekeeping:
 *  - APPROVED past TTL -> EXPIRED (the coupon is already refused at apply time; this releases the
 *    customer+SKU slot and makes the status visible).
 *  - REDEEMED reservations with no order after a grace period -> back to APPROVED.
 */
class ExpireApprovedRequests
{
    private const BATCH = 1000;
    private const ORPHAN_GRACE_SECONDS = 900;

    /**
     * @param RequestResource $resource
     * @param RequestDecider $decider
     * @param PriceMatchRequestRepositoryInterface $repository
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly RequestResource $resource,
        private readonly RequestDecider $decider,
        private readonly PriceMatchRequestRepositoryInterface $repository,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $now = $this->dateTime->gmtTimestamp();

        foreach ($this->resource->getOrphanedReservationIds(
            gmdate('Y-m-d H:i:s', $now - self::ORPHAN_GRACE_SECONDS),
            self::BATCH
        ) as $id) {
            $request = $this->repository->getById($id);
            $this->decider->releaseRedemption($id, $request->getCustomerId(), $request->getSku());
        }

        foreach ($this->resource->getExpiredApprovedIds(gmdate('Y-m-d H:i:s', $now), self::BATCH) as $id) {
            $this->decider->expire($id);
        }
    }
}
