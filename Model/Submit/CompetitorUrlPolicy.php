<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Submit;

use Magento\Framework\Exception\LocalizedException;

/**
 * Parses and allow-lists competitor URLs. Magento never fetches these URLs (no SSRF surface);
 * only the pricing authority receives them.
 */
class CompetitorUrlPolicy
{
    private const MAX_LENGTH = 1024;

    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    /**
     * @param string $url
     * @param string[] $allowedDomains
     * @return string Normalized, lower-cased host
     * @throws LocalizedException
     */
    public function validate(string $url, array $allowedDomains): string
    {
        $url = trim($url);
        // filter_var lets markup such as `'><script>` through in the path; refuse anything RFC 3986 requires encoded.
        if ($url === ''
            || strlen($url) > self::MAX_LENGTH
            || preg_match('/[^\x21-\x7E]|[<>"{}|\\\\^`]/', $url)
            || filter_var($url, FILTER_VALIDATE_URL) === false
        ) {
            throw new LocalizedException(__('Please enter a valid competitor URL.'));
        }

        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string)($parts['host'] ?? ''), '.'));
        // Retailer product pages live on the default port; anything else is not the storefront we allow-listed.
        $port = $parts['port'] ?? null;
        if (!isset(self::DEFAULT_PORTS[$scheme])
            || $host === ''
            || isset($parts['user'])
            || ($port !== null && $port !== self::DEFAULT_PORTS[$scheme])
        ) {
            throw new LocalizedException(__('Please enter a valid competitor URL.'));
        }

        if (!$this->isAllowedHost($host, $allowedDomains)) {
            throw new LocalizedException(__('We do not price match against this retailer.'));
        }
        return $host;
    }

    /**
     * Exact domain or a true subdomain of it: "www.amazon.com" matches "amazon.com", "evilamazon.com" does not.
     *
     * @param string $host
     * @param string[] $allowedDomains
     * @return bool
     */
    public function isAllowedHost(string $host, array $allowedDomains): bool
    {
        foreach ($allowedDomains as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }
        return false;
    }
}
