<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Authority;

use Magento\Framework\App\CacheInterface;
use MagentoGuy\PriceMatch\Model\Config;

/**
 * Shared across all consumer processes via the cache backend (Valkey/Redis), so a failing authority
 * is not hammered by every worker. After the cooldown the next call acts as the half-open probe.
 */
class CircuitBreaker
{
    private const KEY_FAILURES = 'magentoguy_pricematch_cb_failures';
    private const KEY_OPEN_UNTIL = 'magentoguy_pricematch_cb_open_until';

    /**
     * @param CacheInterface $cache
     * @param Config $config
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Config $config
    ) {
    }

    /**
     * @return bool
     */
    public function isOpen(): bool
    {
        return (int)$this->cache->load(self::KEY_OPEN_UNTIL) > time();
    }

    /**
     * @return void
     */
    public function recordSuccess(): void
    {
        $this->cache->remove(self::KEY_FAILURES);
        $this->cache->remove(self::KEY_OPEN_UNTIL);
    }

    /**
     * @return void
     */
    public function recordFailure(): void
    {
        $cooldown = $this->config->getBreakerCooldown();
        $failures = (int)$this->cache->load(self::KEY_FAILURES) + 1;

        if ($failures >= $this->config->getBreakerThreshold()) {
            $this->cache->save((string)(time() + $cooldown), self::KEY_OPEN_UNTIL, [], $cooldown);
            $this->cache->remove(self::KEY_FAILURES);
            return;
        }
        $this->cache->save((string)$failures, self::KEY_FAILURES, [], $cooldown * 5);
    }
}
