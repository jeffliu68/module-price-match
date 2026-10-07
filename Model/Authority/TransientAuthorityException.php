<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Authority;

/**
 * Timeout, connection error, 429 or 5xx: worth retrying later.
 */
class TransientAuthorityException extends \RuntimeException
{
}
