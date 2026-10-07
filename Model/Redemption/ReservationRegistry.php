<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Redemption;

/**
 * Remembers which requests this process reserved for which quote, so failure compensation only
 * releases its own reservations, never a concurrent checkout's.
 */
class ReservationRegistry
{
    /**
     * @var array<int, int[]>
     */
    private array $byQuote = [];

    /**
     * @param int $quoteId
     * @param int $requestId
     * @return void
     */
    public function add(int $quoteId, int $requestId): void
    {
        $this->byQuote[$quoteId][] = $requestId;
    }

    /**
     * Returns and forgets the reservations for a quote.
     *
     * @param int $quoteId
     * @return int[]
     */
    public function pull(int $quoteId): array
    {
        $ids = $this->byQuote[$quoteId] ?? [];
        unset($this->byQuote[$quoteId]);
        return $ids;
    }
}
