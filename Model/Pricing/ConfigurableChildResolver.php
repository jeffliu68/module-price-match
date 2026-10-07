<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Pricing;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Turns a configurable parent plus the shopper's option selection into the child SKU that intake prices.
 * Shared by GraphQL (parent_sku + selected_options UIDs, as in addProductsToCart) and the Luma controller
 * (super_attribute pairs).
 */
class ConfigurableChildResolver
{
    private const UID_PREFIX = 'configurable';

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param Configurable $configurableType
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Configurable $configurableType
    ) {
    }

    /**
     * @param string $parentSku
     * @param string[] $selectedOptions Base64 UIDs of the form configurable/<attribute_id>/<value_id>
     * @param int $storeId
     * @return string Child SKU
     * @throws LocalizedException
     */
    public function resolveBySelectedOptions(string $parentSku, array $selectedOptions, int $storeId): string
    {
        try {
            /** @var Product $parent */
            $parent = $this->productRepository->get($parentSku, false, $storeId);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('The requested product is not available.'));
        }
        if ($parent->getTypeId() !== Configurable::TYPE_CODE) {
            throw new LocalizedException(__('parent_sku must be a configurable product. Send a simple product as sku.'));
        }

        $attributes = [];
        foreach ($selectedOptions as $uid) {
            $decoded = base64_decode((string)$uid, true);
            $parts = $decoded === false ? [] : explode('/', $decoded);
            if (count($parts) !== 3 || $parts[0] !== self::UID_PREFIX || !ctype_digit($parts[1]) || !ctype_digit($parts[2])) {
                throw new LocalizedException(__('One of the selected options is not valid for this product.'));
            }
            $attributes[(int)$parts[1]] = (int)$parts[2];
        }
        return $this->resolveByAttributes($parent, $attributes);
    }

    /**
     * Every configurable attribute must be chosen: getProductByAttributes() alone returns the first child that
     * matches a partial selection, which would price the wrong variant.
     *
     * @param Product $parent
     * @param array<int|string, int|string> $attributes Attribute ID => option value ID
     * @return string Child SKU
     * @throws LocalizedException
     */
    public function resolveByAttributes(Product $parent, array $attributes): string
    {
        $required = [];
        foreach ($this->configurableType->getConfigurableAttributes($parent) as $attribute) {
            $required[] = (int)$attribute->getAttributeId();
        }
        $given = array_map('intval', array_keys(array_filter($attributes, static fn ($v) => (string)$v !== '')));
        sort($required);
        sort($given);
        if (!$required || $required !== $given) {
            throw new LocalizedException(__('Please select the product options first.'));
        }

        $child = $this->configurableType->getProductByAttributes($attributes, $parent);
        if (!$child || !$child->getId()) {
            throw new LocalizedException(__('This combination of options is not available.'));
        }
        return (string)$child->getSku();
    }
}
