<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use MagentoGuy\PriceMatch\Api\SubmitPriceMatchRequestInterface;
use MagentoGuy\PriceMatch\Model\Pricing\ConfigurableChildResolver;

class SubmitPriceMatchRequest implements ResolverInterface
{
    /**
     * @param SubmitPriceMatchRequestInterface $submitService
     * @param CustomerContext $customerContext
     * @param RequestFormatter $formatter
     * @param ConfigurableChildResolver $childResolver
     */
    public function __construct(
        private readonly SubmitPriceMatchRequestInterface $submitService,
        private readonly CustomerContext $customerContext,
        private readonly RequestFormatter $formatter,
        private readonly ConfigurableChildResolver $childResolver
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $customerId = $this->customerContext->requireCustomerId($context);
        $input = $args['input'] ?? [];
        $storeId = $this->customerContext->getStoreId($context);
        $sku = trim((string)($input['sku'] ?? ''));
        $parentSku = trim((string)($input['parent_sku'] ?? ''));
        if (($sku === '') === ($parentSku === '')) {
            throw new GraphQlInputException(__('Provide sku, or parent_sku with selected_options.'));
        }

        try {
            if ($parentSku !== '') {
                $sku = $this->childResolver->resolveBySelectedOptions(
                    $parentSku,
                    (array)($input['selected_options'] ?? []),
                    $storeId
                );
            }
            $request = $this->submitService->execute(
                $customerId,
                $storeId,
                $sku,
                (string)($input['competitor_url'] ?? ''),
                (float)($input['competitor_price'] ?? 0),
                (string)($input['idempotency_key'] ?? '')
            );
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()), $e);
        }

        return $this->formatter->format($request);
    }
}
