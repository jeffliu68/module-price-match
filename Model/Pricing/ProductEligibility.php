<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Pricing;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * Per-product "Allow Price Match" flag. Only an explicit "No" opts out (unset = Yes), and a configurable
 * parent set to "No" opts out all of its variants.
 */
class ProductEligibility
{
    public const ATTRIBUTE_CODE = 'price_match_enabled';

    /**
     * Types a storefront may offer the button on. Configurables resolve to a child before intake.
     */
    public const OFFERED_TYPES = [ProductType::TYPE_SIMPLE, ProductType::TYPE_VIRTUAL, Configurable::TYPE_CODE];

    /**
     * @param ProductResource $productResource
     * @param Configurable $configurableType
     * @param ProductCollectionFactory $collectionFactory
     * @param ResourceConnection $resourceConnection
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        private readonly ProductResource $productResource,
        private readonly Configurable $configurableType,
        private readonly ProductCollectionFactory $collectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly MetadataPool $metadataPool
    ) {
    }

    /**
     * Check a product as loaded (e.g. on the PDP), without looking at parents.
     *
     * @param Product $product
     * @return bool
     */
    public function isEnabledOnProduct(Product $product): bool
    {
        return $this->isEnabledValue($product->getData(self::ATTRIBUTE_CODE));
    }

    /**
     * Check the product and, for a configurable variant, every configurable parent.
     *
     * @param Product $product
     * @param int $storeId
     * @return bool
     */
    public function isEligible(Product $product, int $storeId): bool
    {
        if (!$this->isEnabledOnProduct($product)) {
            return false;
        }
        foreach ($this->configurableType->getParentIdsByChild((int)$product->getId()) as $parentId) {
            $value = $this->productResource->getAttributeRawValue((int)$parentId, self::ATTRIBUTE_CODE, $storeId);
            if (!$this->isEnabledValue($value)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Same rule as isEligible() for many products at once: one query for configurable parents and one for the
     * attribute values, however many products a GraphQL response holds.
     *
     * @param int[] $productIds
     * @param int $storeId
     * @return array<int, bool> Product ID => eligible
     */
    public function getEligibilityMap(array $productIds, int $storeId): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (!$productIds) {
            return [];
        }
        $parents = $this->getParentMap($productIds);
        $flags = $this->getRawFlags(array_merge($productIds, ...array_values($parents)), $storeId);

        $map = [];
        foreach ($productIds as $id) {
            $eligible = $this->isEnabledValue($flags[$id] ?? null);
            foreach ($parents[$id] ?? [] as $parentId) {
                $eligible = $eligible && $this->isEnabledValue($flags[$parentId] ?? null);
            }
            $map[$id] = $eligible;
        }
        return $map;
    }

    /**
     * Child ID => configurable parent entity IDs. Mirrors core's getParentIdsByChild() (link field aware, so
     * staged Commerce row_ids resolve), keeping the child column.
     *
     * @param int[] $childIds
     * @return array<int, int[]>
     */
    private function getParentMap(array $childIds): array
    {
        $connection = $this->resourceConnection->getConnection();
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        $select = $connection->select()
            ->distinct()
            ->from(['l' => $this->resourceConnection->getTableName('catalog_product_super_link')], ['child_id' => 'l.product_id'])
            ->join(
                ['e' => $this->resourceConnection->getTableName('catalog_product_entity')],
                'e.' . $linkField . ' = l.parent_id',
                ['parent_id' => 'e.entity_id']
            )
            ->where('l.product_id IN (?)', $childIds, \Zend_Db::INT_TYPE);

        $map = [];
        foreach ($connection->fetchAll($select) as $row) {
            $map[(int)$row['child_id']][] = (int)$row['parent_id'];
        }
        return $map;
    }

    /**
     * @param int[] $productIds
     * @param int $storeId
     * @return array<int, mixed> Product ID => raw attribute value (null when unset)
     */
    private function getRawFlags(array $productIds, int $storeId): array
    {
        $collection = $this->collectionFactory->create()
            ->setStoreId($storeId)
            ->addIdFilter(array_values(array_unique($productIds)))
            ->addAttributeToSelect(self::ATTRIBUTE_CODE);

        $flags = [];
        foreach ($collection as $product) {
            $flags[(int)$product->getId()] = $product->getData(self::ATTRIBUTE_CODE);
        }
        return $flags;
    }

    /**
     * Unset values come back as null/false/'' from the various loaders; only a stored 0 means "No".
     *
     * @param mixed $value
     * @return bool
     */
    private function isEnabledValue(mixed $value): bool
    {
        return !($value === 0 || $value === '0');
    }
}
