<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use MagentoGuy\PriceMatch\Api\PriceMatchRequestRepositoryInterface;

class PriceMatchRequestQuery implements ResolverInterface
{
    /**
     * @param PriceMatchRequestRepositoryInterface $repository
     * @param CustomerContext $customerContext
     * @param RequestFormatter $formatter
     */
    public function __construct(
        private readonly PriceMatchRequestRepositoryInterface $repository,
        private readonly CustomerContext $customerContext,
        private readonly RequestFormatter $formatter
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $customerId = $this->customerContext->requireCustomerId($context);
        try {
            $request = $this->repository->getForCustomer((int)($args['id'] ?? 0), $customerId);
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__('Price match request not found.'), $e);
        }
        return $this->formatter->format($request);
    }
}
