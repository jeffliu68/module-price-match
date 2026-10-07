<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use MagentoGuy\PriceMatch\Model\Pricing\ProductEligibility;

/**
 * Per-product opt-out. Defaults to Yes; existing products have no stored value and are treated as Yes.
 */
class AddPriceMatchEnabledAttribute implements DataPatchInterface, PatchRevertableInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->addAttribute(Product::ENTITY, ProductEligibility::ATTRIBUTE_CODE, [
            'type' => 'int',
            'label' => 'Allow Price Match',
            'input' => 'boolean',
            'source' => Boolean::class,
            'default' => '1',
            'global' => ScopedAttributeInterface::SCOPE_WEBSITE,
            'required' => false,
            'user_defined' => false,
            'visible' => true,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'is_used_in_grid' => true,
            'is_filterable_in_grid' => true,
            'apply_to' => implode(',', [ProductType::TYPE_SIMPLE, ProductType::TYPE_VIRTUAL, Configurable::TYPE_CODE]),
            'group' => 'General',
            'sort_order' => 200,
            'note' => 'Shoppers can request a price match for this product. For configurable products this '
                . 'setting also applies to all variants.',
        ]);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function revert()
    {
        $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup])
            ->removeAttribute(Product::ENTITY, ProductEligibility::ATTRIBUTE_CODE);
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
