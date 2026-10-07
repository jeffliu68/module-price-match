<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Authority;

use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface;
use MagentoGuy\PriceMatch\Model\Config;

class PricingAuthorityClient
{
    private const CONNECT_TIMEOUT = 2;

    /**
     * @param CurlFactory $curlFactory
     * @param Json $json
     * @param Config $config
     */
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Json $json,
        private readonly Config $config
    ) {
    }

    /**
     * @param PriceMatchRequestInterface $request
     * @return AuthorityResponse
     * @throws TransientAuthorityException
     * @throws PermanentAuthorityException
     */
    public function verify(PriceMatchRequestInterface $request): AuthorityResponse
    {
        $url = $this->config->getAuthorityUrl();
        if ($url === '') {
            throw new PermanentAuthorityException('Pricing authority URL is not configured.');
        }

        $curl = $this->curlFactory->create();
        $curl->setTimeout($this->config->getAuthorityTimeout());
        $curl->setOption(CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        $curl->setOption(CURLOPT_FOLLOWLOCATION, false);
        $curl->addHeader('Content-Type', 'application/json');
        $curl->addHeader('Accept', 'application/json');
        $curl->addHeader('Authorization', 'Bearer ' . $this->config->getAuthorityApiKey());
        // Lets the authority deduplicate our retries.
        $curl->addHeader('Idempotency-Key', 'pm-' . $request->getRequestId());
        $curl->addHeader('X-Attempt', (string)$request->getAttempts());

        $payload = $this->json->serialize([
            'request_id' => $request->getRequestId(),
            'sku' => $request->getSku(),
            'competitor_url' => $request->getCompetitorUrl(),
            'claimed_price' => $request->getClaimedPrice(),
            'currency' => $request->getCurrencyCode(),
        ]);

        try {
            $curl->post($url, $payload);
        } catch (\Exception $e) {
            throw new TransientAuthorityException('Authority unreachable: ' . $e->getMessage(), 0, $e);
        }

        $status = $curl->getStatus();
        if ($status === 429 || $status >= 500 || $status === 0) {
            throw new TransientAuthorityException(sprintf('Authority returned HTTP %d.', $status));
        }
        if ($status !== 200) {
            throw new PermanentAuthorityException(sprintf('Authority returned HTTP %d.', $status));
        }

        try {
            $body = $this->json->unserialize($curl->getBody());
        } catch (\InvalidArgumentException $e) {
            throw new PermanentAuthorityException('Authority returned malformed JSON.', 0, $e);
        }
        if (!is_array($body) || !array_key_exists('verified', $body)) {
            throw new PermanentAuthorityException('Authority response is missing "verified".');
        }

        $price = $body['verified_price'] ?? null;
        return new AuthorityResponse(
            (bool)$body['verified'],
            is_numeric($price) ? (float)$price : null,
            (string)($body['reference'] ?? '')
        );
    }
}
