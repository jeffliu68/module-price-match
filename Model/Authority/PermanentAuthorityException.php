<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Authority;

/**
 * 4xx or malformed response: retrying will not help, a human should look.
 */
class PermanentAuthorityException extends \RuntimeException
{
}
