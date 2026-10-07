<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * @api
 */
interface PriceMatchRequestSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface[]
     */
    public function getItems();

    /**
     * @param \MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
