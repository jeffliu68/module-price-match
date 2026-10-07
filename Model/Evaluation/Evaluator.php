<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Evaluation;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\App\Emulation;
use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface;
use MagentoGuy\PriceMatch\Api\PriceMatchRequestRepositoryInterface;
use MagentoGuy\PriceMatch\Model\Authority\CircuitBreaker;
use MagentoGuy\PriceMatch\Model\Authority\PermanentAuthorityException;
use MagentoGuy\PriceMatch\Model\Authority\PricingAuthorityClient;
use MagentoGuy\PriceMatch\Model\Authority\TransientAuthorityException;
use MagentoGuy\PriceMatch\Model\Config;
use MagentoGuy\PriceMatch\Model\Decision\RequestDecider;
use MagentoGuy\PriceMatch\Model\Pricing\CurrentPriceResolver;
use MagentoGuy\PriceMatch\Model\Pricing\NotEligibleException;
use MagentoGuy\PriceMatch\Model\Pricing\ProductResolver;
use MagentoGuy\PriceMatch\Model\ResourceModel\PriceMatchRequest as RequestResource;
use Psr\Log\LoggerInterface;

/**
 * One evaluation attempt for one request. Safe to call any number of times for the same ID:
 * only a worker that wins the lease on a due PENDING row does anything.
 */
class Evaluator
{
    /**
     * @param RequestResource $resource
     * @param PriceMatchRequestRepositoryInterface $repository
     * @param PricingAuthorityClient $authorityClient
     * @param CircuitBreaker $circuitBreaker
     * @param ProductResolver $productResolver
     * @param CurrentPriceResolver $priceResolver
     * @param CustomerRepositoryInterface $customerRepository
     * @param RulesEngine $rulesEngine
     * @param RequestDecider $decider
     * @param Config $config
     * @param Emulation $emulation
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RequestResource $resource,
        private readonly PriceMatchRequestRepositoryInterface $repository,
        private readonly PricingAuthorityClient $authorityClient,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly ProductResolver $productResolver,
        private readonly CurrentPriceResolver $priceResolver,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly RulesEngine $rulesEngine,
        private readonly RequestDecider $decider,
        private readonly Config $config,
        private readonly Emulation $emulation,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param int $requestId
     * @return void
     */
    public function evaluate(int $requestId): void
    {
        $now = $this->dateTime->gmtTimestamp();
        // Lease outlives the slowest attempt so a crashed worker's row becomes claimable again.
        $lease = gmdate('Y-m-d H:i:s', $now + $this->config->getAuthorityTimeout() * 2 + 60);
        if (!$this->resource->claimForEvaluation($requestId, gmdate('Y-m-d H:i:s', $now), $lease)) {
            $this->logger->info('Price match evaluation skipped (not due, not pending or already claimed).', [
                'request_id' => $requestId,
            ]);
            return;
        }

        $request = $this->repository->getById($requestId);
        $this->emulation->startEnvironmentEmulation($request->getStoreId(), Area::AREA_FRONTEND, true);
        try {
            $this->process($request);
        } catch (\Throwable $e) {
            $this->logger->error('Price match evaluation failed.', ['request_id' => $requestId, 'exception' => $e]);
            $this->decider->sendToReview($request, 'Unexpected error: ' . $e->getMessage());
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    /**
     * @param PriceMatchRequestInterface $request
     * @return void
     * @throws \Exception
     */
    private function process(PriceMatchRequestInterface $request): void
    {
        // Product checks first: no point spending an authority call on a product we would refuse anyway.
        try {
            $product = $this->productResolver->resolve($request->getSku(), $request->getStoreId());
        } catch (NotEligibleException $e) {
            $this->decider->reject($request, RejectReason::NOT_ELIGIBLE);
            return;
        } catch (LocalizedException $e) {
            $this->decider->reject($request, RejectReason::PRODUCT_UNAVAILABLE);
            return;
        }

        if ($this->circuitBreaker->isOpen()) {
            $this->retryOrPark($request, 'Circuit breaker open; authority call skipped.');
            return;
        }

        try {
            $response = $this->authorityClient->verify($request);
            $this->circuitBreaker->recordSuccess();
        } catch (TransientAuthorityException $e) {
            $this->circuitBreaker->recordFailure();
            $this->retryOrPark($request, $e->getMessage());
            return;
        } catch (PermanentAuthorityException $e) {
            $this->decider->sendToReview($request, $e->getMessage());
            return;
        }

        // Re-price now: the catalog price may have changed since intake.
        $groupId = (int)$this->customerRepository->getById($request->getCustomerId())->getGroupId();
        $currentPrice = $this->priceResolver->resolve($product, $groupId, $request->getStoreId());

        $decision = $this->rulesEngine->evaluate(new EvaluationInput(
            $currentPrice,
            $request->getClaimedPrice(),
            $response->verified,
            $response->verifiedPrice,
            $this->config->getMaxDiscountPercent($request->getStoreId()),
            $this->priceResolver->getFloorPrice($product)
        ));

        if ($decision->approved) {
            $this->decider->approve(
                $request,
                $currentPrice,
                $response->verifiedPrice,
                (float)$decision->matchedPrice,
                (float)$decision->discount
            );
            return;
        }
        $this->decider->reject($request, (string)$decision->rejectReason, $currentPrice, $response->verifiedPrice);
    }

    /**
     * @param PriceMatchRequestInterface $request Attempts already include the current one.
     * @param string $error
     * @return void
     */
    private function retryOrPark(PriceMatchRequestInterface $request, string $error): void
    {
        $backoff = $this->config->getRetryBackoff();
        $attempt = $request->getAttempts();
        if ($attempt > count($backoff)) {
            $this->decider->sendToReview($request, sprintf('Gave up after %d attempts: %s', $attempt, $error));
            return;
        }
        $this->decider->scheduleRetry($request, $backoff[$attempt - 1], $error);
    }
}
