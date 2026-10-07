<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Submit;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface;
use MagentoGuy\PriceMatch\Api\PriceMatchRequestRepositoryInterface;
use MagentoGuy\PriceMatch\Api\SubmitPriceMatchRequestInterface;
use MagentoGuy\PriceMatch\Model\Config;
use MagentoGuy\PriceMatch\Model\PriceMatchRequestFactory;
use MagentoGuy\PriceMatch\Model\Pricing\CurrentPriceResolver;
use MagentoGuy\PriceMatch\Model\Pricing\ProductResolver;
use MagentoGuy\PriceMatch\Model\Queue\EvaluationPublisher;
use MagentoGuy\PriceMatch\Model\ResourceModel\PriceMatchRequest as RequestResource;
use MagentoGuy\PriceMatch\Model\Status;
use Psr\Log\LoggerInterface;

class SubmitService implements SubmitPriceMatchRequestInterface
{
    private const MAX_PRICE = 10000000.0;
    private const IDEMPOTENCY_KEY_PATTERN = '/^[A-Za-z0-9_-]{8,64}$/';

    /**
     * @param Config $config
     * @param CompetitorUrlPolicy $urlPolicy
     * @param ProductResolver $productResolver
     * @param CurrentPriceResolver $priceResolver
     * @param CustomerRepositoryInterface $customerRepository
     * @param StoreManagerInterface $storeManager
     * @param PriceMatchRequestRepositoryInterface $repository
     * @param PriceMatchRequestFactory $requestFactory
     * @param RequestResource $requestResource
     * @param EvaluationPublisher $publisher
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly CompetitorUrlPolicy $urlPolicy,
        private readonly ProductResolver $productResolver,
        private readonly CurrentPriceResolver $priceResolver,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly PriceMatchRequestRepositoryInterface $repository,
        private readonly PriceMatchRequestFactory $requestFactory,
        private readonly RequestResource $requestResource,
        private readonly EvaluationPublisher $publisher,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(
        int $customerId,
        int $storeId,
        string $sku,
        string $competitorUrl,
        float $competitorPrice,
        string $idempotencyKey
    ): PriceMatchRequestInterface {
        if (!$this->config->isEnabled($storeId)) {
            throw new LocalizedException(__('Price match is not available.'));
        }
        if (!preg_match(self::IDEMPOTENCY_KEY_PATTERN, $idempotencyKey)) {
            throw new LocalizedException(__('Invalid request key.'));
        }

        $existing = $this->repository->findByIdempotencyKey($customerId, $idempotencyKey);
        if ($existing !== null) {
            return $this->replay($existing, $sku);
        }

        // Round first: validating the raw value let 0.001 through and stored it as 0.00.
        $competitorPrice = is_finite($competitorPrice) ? round($competitorPrice, 2) : NAN;
        if (!is_finite($competitorPrice) || $competitorPrice <= 0 || $competitorPrice >= self::MAX_PRICE) {
            throw new LocalizedException(__('Please enter a valid competitor price.'));
        }
        $host = $this->urlPolicy->validate($competitorUrl, $this->config->getAllowedDomains($storeId));

        $product = $this->productResolver->resolve($sku, $storeId);
        $groupId = (int)$this->customerRepository->getById($customerId)->getGroupId();
        $currentPrice = $this->priceResolver->resolve($product, $groupId, $storeId);
        if ($competitorPrice >= $currentPrice) {
            throw new LocalizedException(__('Good news: our price is already the same or lower.'));
        }

        $this->enforceRateLimits($customerId, $product->getSku(), $storeId);

        /** @var PriceMatchRequestInterface $request */
        $request = $this->requestFactory->create();
        $request->setCustomerId($customerId)
            ->setStoreId($storeId)
            ->setProductId((int)$product->getId())
            ->setSku($product->getSku())
            ->setCompetitorUrl(trim($competitorUrl))
            ->setCompetitorHost($host)
            ->setClaimedPrice($competitorPrice)
            ->setCurrentPrice($currentPrice)
            ->setCurrencyCode((string)$this->storeManager->getStore($storeId)->getBaseCurrencyCode())
            ->setStatus(Status::PENDING)
            ->setIdempotencyKey($idempotencyKey);
        $request->setData(PriceMatchRequestInterface::OPEN_KEY, $customerId . ':' . $product->getSku());

        try {
            $this->repository->save($request);
        } catch (AlreadyExistsException $e) {
            // Lost a race: either the same key was submitted concurrently, or another open request holds the slot.
            $winner = $this->repository->findByIdempotencyKey($customerId, $idempotencyKey);
            if ($winner !== null) {
                return $this->replay($winner, $sku);
            }
            throw new LocalizedException(__('You already have an open price match request for this product.'));
        }

        try {
            $this->publisher->publish((int)$request->getRequestId());
        } catch (\Throwable $e) {
            // Row is committed as PENDING; the requeue cron republishes it. Intake stays available if the broker is down.
            $this->logger->warning(
                'Price match publish failed; will be requeued by cron.',
                ['request_id' => $request->getRequestId(), 'exception' => $e]
            );
        }

        return $this->repository->getById((int)$request->getRequestId());
    }

    /**
     * @param PriceMatchRequestInterface $existing
     * @param string $sku
     * @return PriceMatchRequestInterface
     * @throws LocalizedException
     */
    private function replay(PriceMatchRequestInterface $existing, string $sku): PriceMatchRequestInterface
    {
        if (strcasecmp($existing->getSku(), $sku) !== 0) {
            throw new LocalizedException(__('Invalid request key.'));
        }
        return $existing;
    }

    /**
     * @param int $customerId
     * @param string $sku
     * @param int $storeId
     * @return void
     * @throws LocalizedException
     */
    private function enforceRateLimits(int $customerId, string $sku, int $storeId): void
    {
        $now = $this->dateTime->gmtTimestamp();

        $dailyLimit = $this->config->getDailyLimit($storeId);
        if ($dailyLimit > 0
            && $this->requestResource->countCreatedSince($customerId, gmdate('Y-m-d H:i:s', $now - 86400)) >= $dailyLimit
        ) {
            throw new LocalizedException(__('You have reached the daily limit for price match requests.'));
        }

        $cooldownHours = $this->config->getSkuCooldownHours($storeId);
        if ($cooldownHours > 0
            && $this->requestResource->hasRequestForSkuSince(
                $customerId,
                $sku,
                gmdate('Y-m-d H:i:s', $now - $cooldownHours * 3600)
            )
        ) {
            throw new LocalizedException(__('You recently requested a price match for this product. Please try again later.'));
        }
    }
}
