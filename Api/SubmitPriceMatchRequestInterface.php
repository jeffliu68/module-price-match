<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Api;

use Magento\Framework\Exception\LocalizedException;
use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface;

/**
 * Synchronous intake: validate, rate-limit, persist as PENDING and enqueue. Never evaluates inline.
 *
 * @api
 */
interface SubmitPriceMatchRequestInterface
{
    /**
     * Replaying the same idempotency key returns the original request instead of creating a new one.
     *
     * @param int $customerId
     * @param int $storeId
     * @param string $sku
     * @param string $competitorUrl
     * @param float $competitorPrice
     * @param string $idempotencyKey
     * @return \MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface
     * @throws LocalizedException When the request is rejected at intake (message is customer-safe).
     */
    public function execute(
        int $customerId,
        int $storeId,
        string $sku,
        string $competitorUrl,
        float $competitorPrice,
        string $idempotencyKey
    ): PriceMatchRequestInterface;
}
