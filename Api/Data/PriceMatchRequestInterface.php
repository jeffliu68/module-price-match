<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Api\Data;

/**
 * Price match request entity.
 *
 * @api
 */
interface PriceMatchRequestInterface
{
    public const REQUEST_ID = 'request_id';
    public const CUSTOMER_ID = 'customer_id';
    public const STORE_ID = 'store_id';
    public const PRODUCT_ID = 'product_id';
    public const SKU = 'sku';
    public const COMPETITOR_URL = 'competitor_url';
    public const COMPETITOR_HOST = 'competitor_host';
    public const CLAIMED_PRICE = 'claimed_price';
    public const CURRENT_PRICE = 'current_price';
    public const VERIFIED_PRICE = 'verified_price';
    public const MATCHED_PRICE = 'matched_price';
    public const APPROVED_DISCOUNT = 'approved_discount';
    public const CURRENCY_CODE = 'currency_code';
    public const STATUS = 'status';
    public const REJECT_REASON = 'reject_reason';
    public const COUPON_CODE = 'coupon_code';
    public const IDEMPOTENCY_KEY = 'idempotency_key';
    public const OPEN_KEY = 'open_key';
    public const ATTEMPTS = 'attempts';
    public const NEXT_ATTEMPT_AT = 'next_attempt_at';
    public const LAST_ERROR = 'last_error';
    public const EXPIRES_AT = 'expires_at';
    public const ORDER_ID = 'order_id';
    public const REVIEWED_BY = 'reviewed_by';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * @return int|null
     */
    public function getRequestId(): ?int;

    /**
     * @param int $requestId
     * @return $this
     */
    public function setRequestId(int $requestId): self;

    /**
     * @return int
     */
    public function getCustomerId(): int;

    /**
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId(int $customerId): self;

    /**
     * @return int
     */
    public function getStoreId(): int;

    /**
     * @param int $storeId
     * @return $this
     */
    public function setStoreId(int $storeId): self;

    /**
     * @return int
     */
    public function getProductId(): int;

    /**
     * @param int $productId
     * @return $this
     */
    public function setProductId(int $productId): self;

    /**
     * @return string
     */
    public function getSku(): string;

    /**
     * @param string $sku
     * @return $this
     */
    public function setSku(string $sku): self;

    /**
     * @return string
     */
    public function getCompetitorUrl(): string;

    /**
     * @param string $url
     * @return $this
     */
    public function setCompetitorUrl(string $url): self;

    /**
     * @return string
     */
    public function getCompetitorHost(): string;

    /**
     * @param string $host
     * @return $this
     */
    public function setCompetitorHost(string $host): self;

    /**
     * @return float
     */
    public function getClaimedPrice(): float;

    /**
     * @param float $price
     * @return $this
     */
    public function setClaimedPrice(float $price): self;

    /**
     * @return float|null
     */
    public function getCurrentPrice(): ?float;

    /**
     * @param float|null $price
     * @return $this
     */
    public function setCurrentPrice(?float $price): self;

    /**
     * @return float|null
     */
    public function getVerifiedPrice(): ?float;

    /**
     * @param float|null $price
     * @return $this
     */
    public function setVerifiedPrice(?float $price): self;

    /**
     * @return float|null
     */
    public function getMatchedPrice(): ?float;

    /**
     * @param float|null $price
     * @return $this
     */
    public function setMatchedPrice(?float $price): self;

    /**
     * @return float|null
     */
    public function getApprovedDiscount(): ?float;

    /**
     * @param float|null $discount
     * @return $this
     */
    public function setApprovedDiscount(?float $discount): self;

    /**
     * @return string
     */
    public function getCurrencyCode(): string;

    /**
     * @param string $code
     * @return $this
     */
    public function setCurrencyCode(string $code): self;

    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self;

    /**
     * @return string|null
     */
    public function getRejectReason(): ?string;

    /**
     * @param string|null $reason
     * @return $this
     */
    public function setRejectReason(?string $reason): self;

    /**
     * @return string|null
     */
    public function getCouponCode(): ?string;

    /**
     * @param string|null $code
     * @return $this
     */
    public function setCouponCode(?string $code): self;

    /**
     * @return string
     */
    public function getIdempotencyKey(): string;

    /**
     * @param string $key
     * @return $this
     */
    public function setIdempotencyKey(string $key): self;

    /**
     * @return int
     */
    public function getAttempts(): int;

    /**
     * @return string|null
     */
    public function getNextAttemptAt(): ?string;

    /**
     * @return string|null
     */
    public function getLastError(): ?string;

    /**
     * @return string|null
     */
    public function getExpiresAt(): ?string;

    /**
     * @param string|null $expiresAt
     * @return $this
     */
    public function setExpiresAt(?string $expiresAt): self;

    /**
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * @return string|null
     */
    public function getReviewedBy(): ?string;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string;
}
