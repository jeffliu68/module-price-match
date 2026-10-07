<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Pricing;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\CatalogRule\Model\ResourceModel\Rule as CatalogRuleResource;
use Magento\CatalogRule\Pricing\Price\CatalogRulePrice;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Final price (excl. tax) for one unit as a specific customer group would see it in a specific store.
 *
 * Works without a customer session (queue consumer, token-authenticated GraphQL): tier/group prices
 * read the group from the product, and the catalog rule price is pre-loaded for that group the same
 * way Magento's product collections do it.
 */
class CurrentPriceResolver
{
    /**
     * @param CatalogRuleResource $catalogRuleResource
     * @param TimezoneInterface $timezone
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly CatalogRuleResource $catalogRuleResource,
        private readonly TimezoneInterface $timezone,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param Product $product Must be freshly loaded for $storeId; it is mutated for price calculation.
     * @param int $customerGroupId
     * @param int $storeId
     * @return float
     */
    public function resolve(Product $product, int $customerGroupId, int $storeId): float
    {
        $websiteId = (int)$this->storeManager->getStore($storeId)->getWebsiteId();
        $rulePrice = $this->catalogRuleResource->getRulePrice(
            $this->timezone->scopeDate($storeId),
            $websiteId,
            $customerGroupId,
            (int)$product->getId()
        );

        $product->setCustomerGroupId($customerGroupId);
        $product->setData(CatalogRulePrice::PRICE_CODE, $rulePrice !== false ? (float)$rulePrice : null);

        return round(
            (float)$product->getPriceInfo()->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getBaseAmount(),
            2
        );
    }

    /**
     * Never sell below cost. Uses the native "cost" attribute; no floor when it is empty.
     *
     * @param Product $product
     * @return float|null
     */
    public function getFloorPrice(Product $product): ?float
    {
        $cost = $product->getData('cost');
        return ($cost === null || $cost === '' || (float)$cost <= 0) ? null : (float)$cost;
    }
}
