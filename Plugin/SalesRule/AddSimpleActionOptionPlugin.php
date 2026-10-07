<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Plugin\SalesRule;

use Magento\SalesRule\Model\Rule\Action\SimpleActionOptionsProvider;
use MagentoGuy\PriceMatch\Model\Rule\Action\Discount\PriceMatchFixed;

/**
 * Makes the custom action visible (and preserved on save) in the Cart Price Rule admin form.
 */
class AddSimpleActionOptionPlugin
{
    /**
     * @param SimpleActionOptionsProvider $subject
     * @param array $options
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterToOptionArray(SimpleActionOptionsProvider $subject, array $options): array
    {
        $options[] = [
            'label' => __('Price match: approved amount (system rule only)'),
            'value' => PriceMatchFixed::ACTION,
        ];
        return $options;
    }
}
