<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Resolver;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface;
use MagentoGuy\PriceMatch\Api\PriceMatchRequestRepositoryInterface;

class CustomerPriceMatchRequests implements ResolverInterface
{
    private const MAX_PAGE_SIZE = 50;

    /**
     * @param PriceMatchRequestRepositoryInterface $repository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param CustomerContext $customerContext
     * @param RequestFormatter $formatter
     */
    public function __construct(
        private readonly PriceMatchRequestRepositoryInterface $repository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
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
        $pageSize = (int)($args['pageSize'] ?? 20);
        $currentPage = (int)($args['currentPage'] ?? 1);
        if ($pageSize < 1 || $pageSize > self::MAX_PAGE_SIZE || $currentPage < 1) {
            throw new GraphQlInputException(__('pageSize must be 1-%1 and currentPage must be >= 1.', self::MAX_PAGE_SIZE));
        }

        $criteria = $this->searchCriteriaBuilder
            ->addFilter(PriceMatchRequestInterface::CUSTOMER_ID, $customerId)
            ->addSortOrder(
                $this->sortOrderBuilder->setField(PriceMatchRequestInterface::REQUEST_ID)->setDescendingDirection()->create()
            )
            ->setPageSize($pageSize)
            ->setCurrentPage($currentPage)
            ->create();
        $results = $this->repository->getList($criteria);

        return [
            'items' => array_map([$this->formatter, 'format'], array_values($results->getItems())),
            'total_count' => $results->getTotalCount(),
            'page_info' => [
                'page_size' => $pageSize,
                'current_page' => $currentPage,
                'total_pages' => (int)ceil($results->getTotalCount() / $pageSize),
            ],
        ];
    }
}
