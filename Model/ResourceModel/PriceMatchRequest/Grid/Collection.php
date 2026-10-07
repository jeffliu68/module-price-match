<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\ResourceModel\PriceMatchRequest\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Admin grid source: request rows joined with the customer email.
 */
class Collection extends SearchResult
{
    /**
     * @inheritdoc
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()->joinLeft(
            ['customer' => $this->getTable('customer_entity')],
            'customer.entity_id = main_table.customer_id',
            ['customer_email' => 'customer.email']
        );
        $this->addFilterToMap('customer_email', 'customer.email');
        foreach (['request_id', 'customer_id', 'store_id', 'sku', 'status', 'created_at', 'updated_at'] as $field) {
            $this->addFilterToMap($field, 'main_table.' . $field);
        }
        return $this;
    }
}
