<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Setup\Patch\Data;

use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Api\Data\RuleInterfaceFactory;
use Magento\SalesRule\Api\Data\RuleLabelInterfaceFactory;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use MagentoGuy\PriceMatch\Model\Config;
use MagentoGuy\PriceMatch\Model\Rule\Action\Discount\PriceMatchFixed;

/**
 * Creates the single "Price Match" cart rule that all approved-request coupons hang off.
 *
 * Goes through the rule repository (never raw SQL) so Adobe Commerce content staging (row_id versions)
 * is handled by core. The rule ID is stored in config rather than hard-coded.
 */
class CreatePriceMatchSalesRule implements DataPatchInterface
{
    /**
     * @param RuleRepositoryInterface $ruleRepository
     * @param RuleInterfaceFactory $ruleFactory
     * @param RuleLabelInterfaceFactory $labelFactory
     * @param GroupRepositoryInterface $groupRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     * @param State $appState
     */
    public function __construct(
        private readonly RuleRepositoryInterface $ruleRepository,
        private readonly RuleInterfaceFactory $ruleFactory,
        private readonly RuleLabelInterfaceFactory $labelFactory,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter,
        private readonly State $appState
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        // Rule save (and the staging plugins on Adobe Commerce) need an area; setup:upgrade has none.
        $this->appState->emulateAreaCode(Area::AREA_ADMINHTML, fn () => $this->createRule());
        return $this;
    }

    /**
     * @return void
     */
    private function createRule(): void
    {
        $existingId = (int)$this->scopeConfig->getValue(Config::XML_MASTER_RULE_ID);
        if ($existingId > 0) {
            try {
                $this->ruleRepository->getById($existingId);
                return;
            } catch (NoSuchEntityException $e) {
                // Recreate below.
            }
        }

        $groupIds = [];
        foreach ($this->groupRepository->getList($this->searchCriteriaBuilder->create())->getItems() as $group) {
            $groupIds[] = (int)$group->getId();
        }
        $websiteIds = array_map(static fn ($website) => (int)$website->getId(), $this->storeManager->getWebsites());

        $label = $this->labelFactory->create();
        $label->setStoreId(0)->setStoreLabel('Price Match');

        /** @var RuleInterface $rule */
        $rule = $this->ruleFactory->create();
        $rule->setName('Price Match (system - do not edit)')
            ->setDescription(
                'Managed by MagentoGuy_PriceMatch. Each approved price match request adds one single-use coupon here; '
                . 'the discount comes from the request, not from this rule.'
            )
            ->setIsActive(true)
            ->setWebsiteIds($websiteIds)
            ->setCustomerGroupIds($groupIds)
            ->setCouponType(RuleInterface::COUPON_TYPE_SPECIFIC_COUPON)
            ->setUseAutoGeneration(true)
            // Limits live on each coupon. A rule-level per-customer limit would cap a customer at one match ever.
            ->setUsesPerCoupon(1)
            ->setUsesPerCustomer(0)
            ->setSimpleAction(PriceMatchFixed::ACTION)
            ->setDiscountAmount(0)
            ->setDiscountQty(1)
            ->setDiscountStep(0)
            ->setApplyToShipping(false)
            ->setStopRulesProcessing(false)
            ->setIsRss(false)
            ->setSortOrder(0)
            ->setStoreLabels([$label]);

        $saved = $this->ruleRepository->save($rule);
        $this->configWriter->save(Config::XML_MASTER_RULE_ID, (string)$saved->getRuleId());
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
