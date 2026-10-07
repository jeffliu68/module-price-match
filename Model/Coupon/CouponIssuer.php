<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Coupon;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\SalesRule\Api\CouponRepositoryInterface;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\SalesRule\Api\Data\CouponInterface;
use Magento\SalesRule\Api\Data\CouponInterfaceFactory;
use MagentoGuy\PriceMatch\Model\Config;

/**
 * Adds one generated, single-use coupon to the shared master rule. No new sales rule per request:
 * rule count stays constant, so quote totals collection cost does not grow with approvals.
 */
class CouponIssuer
{
    private const PREFIX = 'PM-';
    // 32 unambiguous symbols, 8 chars = 40 bits; a collision fails the unique index and the request goes to review.
    private const CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * @param Config $config
     * @param CouponInterfaceFactory $couponFactory
     * @param CouponRepositoryInterface $couponRepository
     * @param Random $random
     * @param DateTime $dateTime
     * @param RuleRepositoryInterface $ruleRepository
     */
    public function __construct(
        private readonly Config $config,
        private readonly CouponInterfaceFactory $couponFactory,
        private readonly CouponRepositoryInterface $couponRepository,
        private readonly Random $random,
        private readonly DateTime $dateTime,
        private readonly RuleRepositoryInterface $ruleRepository
    ) {
    }

    /**
     * @param string $expiresAtUtc
     * @return string The coupon code
     * @throws LocalizedException
     */
    public function issue(string $expiresAtUtc): string
    {
        $ruleId = $this->config->getMasterRuleId();
        if ($ruleId === null) {
            throw new LocalizedException(__('Price match sales rule is not configured.'));
        }
        // A coupon on an inactive rule would be "approved" yet refused at checkout; fail so the request goes to review.
        if (!$this->ruleRepository->getById($ruleId)->getIsActive()) {
            throw new LocalizedException(__('Price match sales rule is inactive.'));
        }

        $code = self::PREFIX
            . $this->random->getRandomString(4, self::CHARSET) . '-'
            . $this->random->getRandomString(4, self::CHARSET);

        /** @var CouponInterface $coupon */
        $coupon = $this->couponFactory->create();
        $coupon->setRuleId($ruleId)
            ->setCode($code)
            ->setType(CouponInterface::TYPE_GENERATED)
            ->setExpirationDate($expiresAtUtc)
            ->setCreatedAt($this->dateTime->gmtDate());
        $this->couponRepository->save($coupon);

        return $code;
    }
}
