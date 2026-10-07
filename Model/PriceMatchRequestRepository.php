<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface;
use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestSearchResultsInterface;
use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestSearchResultsInterfaceFactory;
use MagentoGuy\PriceMatch\Api\PriceMatchRequestRepositoryInterface;
use MagentoGuy\PriceMatch\Model\ResourceModel\PriceMatchRequest as ResourceModel;
use MagentoGuy\PriceMatch\Model\ResourceModel\PriceMatchRequest\CollectionFactory;

class PriceMatchRequestRepository implements PriceMatchRequestRepositoryInterface
{
    /**
     * @param ResourceModel $resource
     * @param PriceMatchRequestFactory $requestFactory
     * @param CollectionFactory $collectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param PriceMatchRequestSearchResultsInterfaceFactory $searchResultsFactory
     */
    public function __construct(
        private readonly ResourceModel $resource,
        private readonly PriceMatchRequestFactory $requestFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly PriceMatchRequestSearchResultsInterfaceFactory $searchResultsFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function save(PriceMatchRequestInterface $request): PriceMatchRequestInterface
    {
        try {
            $this->resource->save($request);
        } catch (AlreadyExistsException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save the price match request.'), $e);
        }
        return $request;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $requestId): PriceMatchRequestInterface
    {
        $request = $this->requestFactory->create();
        $this->resource->load($request, $requestId);
        if (!$request->getRequestId()) {
            throw NoSuchEntityException::singleField(PriceMatchRequestInterface::REQUEST_ID, $requestId);
        }
        return $request;
    }

    /**
     * @inheritdoc
     */
    public function getForCustomer(int $requestId, int $customerId): PriceMatchRequestInterface
    {
        $request = $this->getById($requestId);
        if ($request->getCustomerId() !== $customerId) {
            // Same error as "not found" so IDs can't be probed across customers.
            throw NoSuchEntityException::singleField(PriceMatchRequestInterface::REQUEST_ID, $requestId);
        }
        return $request;
    }

    /**
     * @inheritdoc
     */
    public function findByCouponCode(string $couponCode): ?PriceMatchRequestInterface
    {
        $request = $this->requestFactory->create();
        $this->resource->load($request, $couponCode, PriceMatchRequestInterface::COUPON_CODE);
        return $request->getRequestId() ? $request : null;
    }

    /**
     * @inheritdoc
     */
    public function findByIdempotencyKey(int $customerId, string $idempotencyKey): ?PriceMatchRequestInterface
    {
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter(PriceMatchRequestInterface::CUSTOMER_ID, $customerId)
            ->addFieldToFilter(PriceMatchRequestInterface::IDEMPOTENCY_KEY, $idempotencyKey)
            ->setPageSize(1);
        $item = $collection->getFirstItem();
        return $item->getRequestId() ? $item : null;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): PriceMatchRequestSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $results = $this->searchResultsFactory->create();
        $results->setSearchCriteria($searchCriteria);
        $results->setItems($collection->getItems());
        $results->setTotalCount($collection->getSize());
        return $results;
    }
}
