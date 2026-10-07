<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\ResourceModel\PriceMatchRequest;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MagentoGuy\PriceMatch\Model\PriceMatchRequest;
use MagentoGuy\PriceMatch\Model\ResourceModel\PriceMatchRequest as ResourceModel;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'request_id';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(PriceMatchRequest::class, ResourceModel::class);
    }
}
