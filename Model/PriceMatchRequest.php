<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model;

use Magento\Framework\Model\AbstractModel;
use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface;
use MagentoGuy\PriceMatch\Model\ResourceModel\PriceMatchRequest as ResourceModel;

class PriceMatchRequest extends AbstractModel implements PriceMatchRequestInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'magentoguy_price_match_request';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }

    /**
     * @param string $key
     * @return float|null
     */
    private function getNullableFloat(string $key): ?float
    {
        $value = $this->getData($key);
        return $value === null ? null : (float)$value;
    }

    public function getRequestId(): ?int
    {
        $id = $this->getData(self::REQUEST_ID);
        return $id === null ? null : (int)$id;
    }

    public function setRequestId(int $requestId): PriceMatchRequestInterface
    {
        return $this->setData(self::REQUEST_ID, $requestId);
    }

    public function getCustomerId(): int
    {
        return (int)$this->getData(self::CUSTOMER_ID);
    }

    public function setCustomerId(int $customerId): PriceMatchRequestInterface
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    public function getStoreId(): int
    {
        return (int)$this->getData(self::STORE_ID);
    }

    public function setStoreId(int $storeId): PriceMatchRequestInterface
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    public function getProductId(): int
    {
        return (int)$this->getData(self::PRODUCT_ID);
    }

    public function setProductId(int $productId): PriceMatchRequestInterface
    {
        return $this->setData(self::PRODUCT_ID, $productId);
    }

    public function getSku(): string
    {
        return (string)$this->getData(self::SKU);
    }

    public function setSku(string $sku): PriceMatchRequestInterface
    {
        return $this->setData(self::SKU, $sku);
    }

    public function getCompetitorUrl(): string
    {
        return (string)$this->getData(self::COMPETITOR_URL);
    }

    public function setCompetitorUrl(string $url): PriceMatchRequestInterface
    {
        return $this->setData(self::COMPETITOR_URL, $url);
    }

    public function getCompetitorHost(): string
    {
        return (string)$this->getData(self::COMPETITOR_HOST);
    }

    public function setCompetitorHost(string $host): PriceMatchRequestInterface
    {
        return $this->setData(self::COMPETITOR_HOST, $host);
    }

    public function getClaimedPrice(): float
    {
        return (float)$this->getData(self::CLAIMED_PRICE);
    }

    public function setClaimedPrice(float $price): PriceMatchRequestInterface
    {
        return $this->setData(self::CLAIMED_PRICE, $price);
    }

    public function getCurrentPrice(): ?float
    {
        return $this->getNullableFloat(self::CURRENT_PRICE);
    }

    public function setCurrentPrice(?float $price): PriceMatchRequestInterface
    {
        return $this->setData(self::CURRENT_PRICE, $price);
    }

    public function getVerifiedPrice(): ?float
    {
        return $this->getNullableFloat(self::VERIFIED_PRICE);
    }

    public function setVerifiedPrice(?float $price): PriceMatchRequestInterface
    {
        return $this->setData(self::VERIFIED_PRICE, $price);
    }

    public function getMatchedPrice(): ?float
    {
        return $this->getNullableFloat(self::MATCHED_PRICE);
    }

    public function setMatchedPrice(?float $price): PriceMatchRequestInterface
    {
        return $this->setData(self::MATCHED_PRICE, $price);
    }

    public function getApprovedDiscount(): ?float
    {
        return $this->getNullableFloat(self::APPROVED_DISCOUNT);
    }

    public function setApprovedDiscount(?float $discount): PriceMatchRequestInterface
    {
        return $this->setData(self::APPROVED_DISCOUNT, $discount);
    }

    public function getCurrencyCode(): string
    {
        return (string)$this->getData(self::CURRENCY_CODE);
    }

    public function setCurrencyCode(string $code): PriceMatchRequestInterface
    {
        return $this->setData(self::CURRENCY_CODE, $code);
    }

    public function getStatus(): string
    {
        return (string)($this->getData(self::STATUS) ?: Status::PENDING);
    }

    public function setStatus(string $status): PriceMatchRequestInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getRejectReason(): ?string
    {
        return $this->getData(self::REJECT_REASON);
    }

    public function setRejectReason(?string $reason): PriceMatchRequestInterface
    {
        return $this->setData(self::REJECT_REASON, $reason);
    }

    public function getCouponCode(): ?string
    {
        return $this->getData(self::COUPON_CODE);
    }

    public function setCouponCode(?string $code): PriceMatchRequestInterface
    {
        return $this->setData(self::COUPON_CODE, $code);
    }

    public function getIdempotencyKey(): string
    {
        return (string)$this->getData(self::IDEMPOTENCY_KEY);
    }

    public function setIdempotencyKey(string $key): PriceMatchRequestInterface
    {
        return $this->setData(self::IDEMPOTENCY_KEY, $key);
    }

    public function getAttempts(): int
    {
        return (int)$this->getData(self::ATTEMPTS);
    }

    public function getNextAttemptAt(): ?string
    {
        return $this->getData(self::NEXT_ATTEMPT_AT);
    }

    public function getLastError(): ?string
    {
        return $this->getData(self::LAST_ERROR);
    }

    public function getExpiresAt(): ?string
    {
        return $this->getData(self::EXPIRES_AT);
    }

    public function setExpiresAt(?string $expiresAt): PriceMatchRequestInterface
    {
        return $this->setData(self::EXPIRES_AT, $expiresAt);
    }

    public function getOrderId(): ?int
    {
        $id = $this->getData(self::ORDER_ID);
        return $id === null ? null : (int)$id;
    }

    public function getReviewedBy(): ?string
    {
        return $this->getData(self::REVIEWED_BY);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }
}
