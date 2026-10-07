<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Pricing;

use Magento\Framework\Exception\LocalizedException;

/**
 * The product exists and is salable, but an admin has excluded it from price matching.
 */
class NotEligibleException extends LocalizedException
{
}
