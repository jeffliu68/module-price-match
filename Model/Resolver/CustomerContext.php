<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Resolver;

use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\GraphQl\Model\Query\ContextInterface;

class CustomerContext
{
    /**
     * @param ContextInterface $context
     * @return int Customer ID
     * @throws GraphQlAuthorizationException
     */
    public function requireCustomerId(ContextInterface $context): int
    {
        if ($context->getExtensionAttributes()->getIsCustomer() !== true || !$context->getUserId()) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }
        return (int)$context->getUserId();
    }

    /**
     * @param ContextInterface $context
     * @return int
     */
    public function getStoreId(ContextInterface $context): int
    {
        return (int)$context->getExtensionAttributes()->getStore()->getId();
    }
}
