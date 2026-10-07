<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Evaluation;

class Decision
{
    /**
     * @param bool $approved
     * @param string|null $rejectReason One of RejectReason::* when not approved.
     * @param float|null $matchedPrice
     * @param float|null $discount Per-unit discount, when approved.
     */
    private function __construct(
        public readonly bool $approved,
        public readonly ?string $rejectReason,
        public readonly ?float $matchedPrice,
        public readonly ?float $discount
    ) {
    }

    /**
     * @param float $matchedPrice
     * @param float $discount
     * @return self
     */
    public static function approve(float $matchedPrice, float $discount): self
    {
        return new self(true, null, $matchedPrice, $discount);
    }

    /**
     * @param string $reason
     * @param float|null $matchedPrice
     * @return self
     */
    public static function reject(string $reason, ?float $matchedPrice = null): self
    {
        return new self(false, $reason, $matchedPrice, null);
    }
}
