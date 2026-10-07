<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Resolver\StoreConfig;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use MagentoGuy\PriceMatch\Model\Config;

/**
 * storeConfig.price_match_allowed_domains as a normalised list; admins may separate entries with commas or
 * new lines, which a storefront should not have to parse.
 */
class AllowedDomains implements ResolverInterface
{
    /**
     * @param Config $config
     */
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        return $this->config->getAllowedDomains((int)$context->getExtensionAttributes()->getStore()->getId());
    }
}
