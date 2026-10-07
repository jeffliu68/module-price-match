<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Authority;

class AuthorityResponse
{
    /**
     * @param bool $verified Authority confirms the competitor sells the item at a price it can attest to.
     * @param float|null $verifiedPrice The attested price (may differ from what the customer claimed).
     * @param string $reference Authority-side reference for audit.
     */
    public function __construct(
        public readonly bool $verified,
        public readonly ?float $verifiedPrice,
        public readonly string $reference
    ) {
    }
}
