<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_ENABLED = 'magentoguy_pricematch/general/enabled';
    public const XML_ALLOWED_DOMAINS = 'magentoguy_pricematch/general/allowed_domains';
    public const XML_MAX_DISCOUNT_PERCENT = 'magentoguy_pricematch/general/max_discount_percent';
    public const XML_COUPON_TTL_HOURS = 'magentoguy_pricematch/general/coupon_ttl_hours';
    public const XML_DAILY_LIMIT = 'magentoguy_pricematch/general/daily_limit';
    public const XML_SKU_COOLDOWN_HOURS = 'magentoguy_pricematch/general/sku_cooldown_hours';
    public const XML_AUTHORITY_URL = 'magentoguy_pricematch/authority/url';
    public const XML_AUTHORITY_API_KEY = 'magentoguy_pricematch/authority/api_key';
    public const XML_AUTHORITY_TIMEOUT = 'magentoguy_pricematch/authority/timeout';
    public const XML_RETRY_BACKOFF = 'magentoguy_pricematch/authority/retry_backoff';
    public const XML_BREAKER_THRESHOLD = 'magentoguy_pricematch/authority/breaker_threshold';
    public const XML_BREAKER_COOLDOWN = 'magentoguy_pricematch/authority/breaker_cooldown';
    public const XML_EMAIL_IDENTITY = 'magentoguy_pricematch/email/identity';
    public const XML_EMAIL_APPROVED_TEMPLATE = 'magentoguy_pricematch/email/approved_template';
    public const XML_EMAIL_REJECTED_TEMPLATE = 'magentoguy_pricematch/email/rejected_template';
    public const XML_MASTER_RULE_ID = 'magentoguy_pricematch/coupon/rule_id';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Lower-cased registrable domains, one per line or comma separated in config.
     *
     * @param int|null $storeId
     * @return string[]
     */
    public function getAllowedDomains(?int $storeId = null): array
    {
        $raw = (string)$this->scopeConfig->getValue(self::XML_ALLOWED_DOMAINS, ScopeInterface::SCOPE_STORE, $storeId);
        $domains = preg_split('/[\s,]+/', strtolower($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique(array_map(static fn ($d) => ltrim($d, '.'), $domains)));
    }

    /**
     * @param int|null $storeId
     * @return float
     */
    public function getMaxDiscountPercent(?int $storeId = null): float
    {
        return (float)$this->scopeConfig->getValue(
            self::XML_MAX_DISCOUNT_PERCENT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param int|null $storeId
     * @return int
     */
    public function getCouponTtlHours(?int $storeId = null): int
    {
        $hours = (int)$this->scopeConfig->getValue(self::XML_COUPON_TTL_HOURS, ScopeInterface::SCOPE_STORE, $storeId);
        return max(1, $hours);
    }

    /**
     * @param int|null $storeId
     * @return int 0 disables the limit
     */
    public function getDailyLimit(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_DAILY_LIMIT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return int 0 disables the cooldown
     */
    public function getSkuCooldownHours(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_SKU_COOLDOWN_HOURS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @return string
     */
    public function getAuthorityUrl(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_AUTHORITY_URL);
    }

    /**
     * Stored encrypted; config.xml declares the Encrypted backend model, so the config reader decrypts on load.
     *
     * @return string
     */
    public function getAuthorityApiKey(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_AUTHORITY_API_KEY);
    }

    /**
     * @return int seconds
     */
    public function getAuthorityTimeout(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_AUTHORITY_TIMEOUT));
    }

    /**
     * Delay before retry N (1-based). Its length is the number of retries after the first attempt.
     *
     * @return int[] seconds
     */
    public function getRetryBackoff(): array
    {
        $raw = (string)$this->scopeConfig->getValue(self::XML_RETRY_BACKOFF);
        $parts = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_filter(array_map('intval', $parts), static fn (int $s) => $s > 0));
    }

    /**
     * @return int
     */
    public function getBreakerThreshold(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_BREAKER_THRESHOLD));
    }

    /**
     * @return int seconds
     */
    public function getBreakerCooldown(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_BREAKER_COOLDOWN));
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getEmailIdentity(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_EMAIL_IDENTITY, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param bool $approved
     * @param int|null $storeId
     * @return string
     */
    public function getEmailTemplate(bool $approved, ?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            $approved ? self::XML_EMAIL_APPROVED_TEMPLATE : self::XML_EMAIL_REJECTED_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return int|null
     */
    public function getMasterRuleId(): ?int
    {
        $id = (int)$this->scopeConfig->getValue(self::XML_MASTER_RULE_ID);
        return $id > 0 ? $id : null;
    }
}
