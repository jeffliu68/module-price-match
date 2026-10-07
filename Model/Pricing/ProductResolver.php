<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Pricing;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Resolves the purchasable unit we match against: a simple/virtual SKU (configurables must be resolved
 * to the selected child by the caller), enabled, assigned to the store's website and salable.
 */
class ProductResolver
{
    private const SUPPORTED_TYPES = [ProductType::TYPE_SIMPLE, ProductType::TYPE_VIRTUAL];

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     * @param ProductEligibility $eligibility
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductEligibility $eligibility
    ) {
    }

    /**
     * @param string $sku
     * @param int $storeId
     * @return ProductInterface&Product
     * @throws NotEligibleException When an admin excluded the product (or its configurable parent).
     * @throws LocalizedException
     */
    public function resolve(string $sku, int $storeId): ProductInterface
    {
        try {
            /** @var Product $product */
            $product = $this->productRepository->get($sku, false, $storeId, true);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('The requested product is not available.'));
        }

        if (!in_array($product->getTypeId(), self::SUPPORTED_TYPES, true)) {
            throw new LocalizedException(__('Please select the specific product options before requesting a price match.'));
        }

        $websiteId = (int)$this->storeManager->getStore($storeId)->getWebsiteId();
        if ((int)$product->getStatus() !== ProductStatus::STATUS_ENABLED
            || !in_array($websiteId, array_map('intval', (array)$product->getWebsiteIds()), true)
            || !$product->isSalable()
        ) {
            throw new LocalizedException(__('The requested product is not available.'));
        }

        if (!$this->eligibility->isEligible($product, $storeId)) {
            throw new NotEligibleException(__('This product is not eligible for price matching.'));
        }

        return $product;
    }
}
