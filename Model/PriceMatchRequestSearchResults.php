<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model;

use Magento\Framework\Api\SearchResults;
use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestSearchResultsInterface;

class PriceMatchRequestSearchResults extends SearchResults implements PriceMatchRequestSearchResultsInterface
{
}
