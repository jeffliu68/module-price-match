<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Decision;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\App\Emulation;
use MagentoGuy\PriceMatch\Api\PriceMatchRequestRepositoryInterface;
use MagentoGuy\PriceMatch\Model\Config;
use MagentoGuy\PriceMatch\Model\Evaluation\EvaluationInput;
use MagentoGuy\PriceMatch\Model\Evaluation\RejectReason;
use MagentoGuy\PriceMatch\Model\Evaluation\RulesEngine;
use MagentoGuy\PriceMatch\Model\Pricing\CurrentPriceResolver;
use MagentoGuy\PriceMatch\Model\Pricing\ProductResolver;
use MagentoGuy\PriceMatch\Model\Status;

/**
 * Admin override for PENDING / NEEDS_REVIEW requests. An admin approval stands in for the authority
 * and may exceed the automatic cap, but still re-prices now and never goes below cost.
 */
class ManualReview
{
    /**
     * @param PriceMatchRequestRepositoryInterface $repository
     * @param RequestDecider $decider
     * @param RulesEngine $rulesEngine
     * @param ProductResolver $productResolver
     * @param CurrentPriceResolver $priceResolver
     * @param CustomerRepositoryInterface $customerRepository
     * @param RejectReason $rejectReason
     * @param Config $config
     * @param Emulation $emulation
     */
    public function __construct(
        private readonly PriceMatchRequestRepositoryInterface $repository,
        private readonly RequestDecider $decider,
        private readonly RulesEngine $rulesEngine,
        private readonly ProductResolver $productResolver,
        private readonly CurrentPriceResolver $priceResolver,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly RejectReason $rejectReason,
        private readonly Config $config,
        private readonly Emulation $emulation
    ) {
    }

    /**
     * @param int $requestId
     * @param string $adminUser
     * @param string|null $note Internal note for the audit history
     * @return void
     * @throws LocalizedException
     */
    public function approve(int $requestId, string $adminUser, ?string $note = null): void
    {
        $request = $this->repository->getById($requestId);
        $this->assertReviewable($request->getStatus());

        $this->emulation->startEnvironmentEmulation($request->getStoreId(), Area::AREA_FRONTEND, true);
        try {
            $product = $this->productResolver->resolve($request->getSku(), $request->getStoreId());
            $groupId = (int)$this->customerRepository->getById($request->getCustomerId())->getGroupId();
            $currentPrice = $this->priceResolver->resolve($product, $groupId, $request->getStoreId());
            $floor = $this->priceResolver->getFloorPrice($product);
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }

        $input = new EvaluationInput(
            $currentPrice,
            $request->getClaimedPrice(),
            true,
            $request->getVerifiedPrice(),
            $this->config->getMaxDiscountPercent($request->getStoreId()),
            $floor
        );
        $decision = $this->rulesEngine->evaluatePrice(
            $request->getVerifiedPrice() ?? $request->getClaimedPrice(),
            $input,
            false
        );
        if (!$decision->approved) {
            throw new LocalizedException(__(
                'Request #%1 cannot be approved: %2 (current price %3).',
                $requestId,
                $this->rejectReason->getLabel($decision->rejectReason),
                $currentPrice
            ));
        }

        if (!$this->decider->approve(
            $request,
            $currentPrice,
            $request->getVerifiedPrice(),
            (float)$decision->matchedPrice,
            (float)$decision->discount,
            $adminUser,
            $note
        )) {
            throw new LocalizedException(__('Request #%1 was changed by another process. Please reload.', $requestId));
        }
    }

    /**
     * @param int $requestId
     * @param string $adminUser
     * @param string $reason One of RejectReason::ADMIN_CHOICES; drives the customer email wording
     * @param string|null $note Internal note for the audit history; never sent to the customer
     * @return void
     * @throws LocalizedException
     */
    public function reject(
        int $requestId,
        string $adminUser,
        string $reason = RejectReason::REJECTED_BY_ADMIN,
        ?string $note = null
    ): void {
        if (!in_array($reason, RejectReason::ADMIN_CHOICES, true)) {
            throw new LocalizedException(__('Please choose a valid reject reason.'));
        }
        $request = $this->repository->getById($requestId);
        $this->assertReviewable($request->getStatus());
        if (!$this->decider->reject($request, $reason, null, null, $adminUser, $note)) {
            throw new LocalizedException(__('Request #%1 was changed by another process. Please reload.', $requestId));
        }
    }

    /**
     * @param string $status
     * @return void
     * @throws LocalizedException
     */
    private function assertReviewable(string $status): void
    {
        if (!in_array($status, [Status::PENDING, Status::NEEDS_REVIEW], true)) {
            throw new LocalizedException(__('Only pending or needs-review requests can be approved or rejected.'));
        }
    }
}
