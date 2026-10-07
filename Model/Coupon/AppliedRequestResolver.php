<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Coupon;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\SalesRule\Model\Quote\GetCouponCodes;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\SelectRuleCoupon;
use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface;
use MagentoGuy\PriceMatch\Api\PriceMatchRequestRepositoryInterface;
use MagentoGuy\PriceMatch\Model\Config;
use MagentoGuy\PriceMatch\Model\Status;

/**
 * Maps "master rule + quote" to the approved request behind the coupon on that quote, and decides whether
 * this quote may use it. Uses core's coupon resolution, so it works with single- and multi-coupon carts.
 */
class AppliedRequestResolver
{
    /**
     * @param Config $config
     * @param GetCouponCodes $getCouponCodes
     * @param SelectRuleCoupon $selectRuleCoupon
     * @param PriceMatchRequestRepositoryInterface $repository
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly Config $config,
        private readonly GetCouponCodes $getCouponCodes,
        private readonly SelectRuleCoupon $selectRuleCoupon,
        private readonly PriceMatchRequestRepositoryInterface $repository,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @param Rule $rule
     * @return bool
     */
    public function isMasterRule(Rule $rule): bool
    {
        $masterId = $this->config->getMasterRuleId();
        // getRuleId(), not getId(): on Adobe Commerce getId() is the staging row_id.
        return $masterId !== null && (int)$rule->getRuleId() === $masterId;
    }

    /**
     * @param Rule $rule
     * @param CartInterface $quote
     * @return PriceMatchRequestInterface|null
     */
    public function resolve(Rule $rule, CartInterface $quote): ?PriceMatchRequestInterface
    {
        $code = $this->selectRuleCoupon->execute($rule, $this->getCouponCodes->execute($quote));
        if ($code === null || $code === '') {
            return null;
        }
        // Deliberately uncached: long-running consumers (e.g. async order placement) must see the live status.
        return $this->repository->findByCouponCode($code);
    }

    /**
     * The coupon is bound to one customer, one SKU, an APPROVED (not yet redeemed/expired) request and a TTL.
     *
     * @param PriceMatchRequestInterface $request
     * @param CartInterface&Quote $quote
     * @return bool
     */
    public function isUsableBy(PriceMatchRequestInterface $request, CartInterface $quote): bool
    {
        if ($request->getStatus() !== Status::APPROVED
            || !$quote->getCustomerId()
            || (int)$quote->getCustomerId() !== $request->getCustomerId()
        ) {
            return false;
        }

        $expiresAt = $request->getExpiresAt();
        if ($expiresAt === null || strtotime($expiresAt . ' UTC') <= $this->dateTime->gmtTimestamp()) {
            return false;
        }

        foreach ($quote->getAllVisibleItems() as $item) {
            if (strcasecmp((string)$item->getSku(), $request->getSku()) === 0) {
                return true;
            }
        }
        return false;
    }
}
