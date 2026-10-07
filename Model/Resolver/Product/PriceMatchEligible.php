<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Resolver\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResponse;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use MagentoGuy\PriceMatch\Model\Config;
use MagentoGuy\PriceMatch\Model\Pricing\ProductEligibility;

/**
 * ProductInterface.price_match_eligible, resolved for the whole response at once so a category listing
 * costs two queries, not two per product. Same for every customer, so it stays cacheable.
 */
class PriceMatchEligible implements BatchResolverInterface
{
    /**
     * @param ProductEligibility $eligibility
     * @param Config $config
     */
    public function __construct(
        private readonly ProductEligibility $eligibility,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(ContextInterface $context, Field $field, array $requests): BatchResponse
    {
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $enabled = $this->config->isEnabled($storeId);

        $ids = [];
        foreach ($requests as $request) {
            $product = $this->productOf($request->getValue());
            if ($enabled && $product && in_array($product->getTypeId(), ProductEligibility::OFFERED_TYPES, true)) {
                $ids[] = (int)$product->getId();
            }
        }
        $map = $this->eligibility->getEligibilityMap($ids, $storeId);

        $response = new BatchResponse();
        foreach ($requests as $request) {
            $product = $this->productOf($request->getValue());
            $response->addResponse($request, $product !== null && ($map[(int)$product->getId()] ?? false));
        }
        return $response;
    }

    /**
     * @param array|null $value
     * @return Product|null
     */
    private function productOf(?array $value): ?Product
    {
        $model = $value['model'] ?? null;
        return $model instanceof Product && $model->getId() ? $model : null;
    }
}
