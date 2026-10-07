<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Evaluation;

class EvaluationInput
{
    /**
     * @param float $currentPrice Our final price for the customer right now.
     * @param float $claimedPrice What the customer submitted.
     * @param bool $verified Authority verdict.
     * @param float|null $verifiedPrice Authority's attested price; falls back to the claimed price when absent.
     * @param float $maxDiscountPercent Policy cap.
     * @param float|null $floorPrice Never match below this (product cost); null = no floor.
     */
    public function __construct(
        public readonly float $currentPrice,
        public readonly float $claimedPrice,
        public readonly bool $verified,
        public readonly ?float $verifiedPrice,
        public readonly float $maxDiscountPercent,
        public readonly ?float $floorPrice
    ) {
    }
}
