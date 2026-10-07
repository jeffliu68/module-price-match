<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Redemption;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\SalesRule\Model\Quote\GetCouponCodes;
use MagentoGuy\PriceMatch\Api\PriceMatchRequestRepositoryInterface;
use MagentoGuy\PriceMatch\Model\Decision\RequestDecider;
use Psr\Log\LoggerInterface;

/**
 * Reserve-then-confirm redemption around order placement:
 *  submit_before  -> reserve (APPROVED -> REDEEMED via CAS); losing the race aborts the order
 *  submit_failure -> release this process's reservations
 *  submit_success -> attach the order ID
 * Reservations orphaned by a crash between reserve and placement are released by cron.
 */
class RedemptionManager
{
    /**
     * @param GetCouponCodes $getCouponCodes
     * @param PriceMatchRequestRepositoryInterface $repository
     * @param RequestDecider $decider
     * @param ReservationRegistry $registry
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly GetCouponCodes $getCouponCodes,
        private readonly PriceMatchRequestRepositoryInterface $repository,
        private readonly RequestDecider $decider,
        private readonly ReservationRegistry $registry,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param Quote $quote
     * @return void
     * @throws LocalizedException
     */
    public function reserve(Quote $quote): void
    {
        foreach ($this->getCouponCodes->execute($quote) as $code) {
            if (!is_string($code) || $code === '') {
                continue;
            }
            $request = $this->repository->findByCouponCode($code);
            if ($request === null) {
                continue;
            }
            if (!$this->decider->reserveRedemption((int)$request->getRequestId())) {
                throw new LocalizedException(
                    __('The price match coupon "%1" is no longer valid. Please remove it and try again.', $code)
                );
            }
            $this->registry->add((int)$quote->getId(), (int)$request->getRequestId());
        }
    }

    /**
     * @param Quote $quote
     * @return void
     */
    public function release(Quote $quote): void
    {
        foreach ($this->registry->pull((int)$quote->getId()) as $requestId) {
            $request = $this->repository->getById($requestId);
            $this->decider->releaseRedemption($requestId, $request->getCustomerId(), $request->getSku());
        }
    }

    /**
     * @param Quote $quote
     * @param int $orderId
     * @return void
     */
    public function confirm(Quote $quote, int $orderId): void
    {
        foreach ($this->registry->pull((int)$quote->getId()) as $requestId) {
            if (!$this->decider->attachOrder($requestId, $orderId)) {
                $this->logger->warning('Price match: could not attach order to reservation.', [
                    'request_id' => $requestId,
                    'order_id' => $orderId,
                ]);
            }
        }
    }
}
