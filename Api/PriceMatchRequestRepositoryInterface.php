<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface;
use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestSearchResultsInterface;

/**
 * @api
 */
interface PriceMatchRequestRepositoryInterface
{
    /**
     * @param \MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface $request
     * @return \MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface
     * @throws CouldNotSaveException
     * @throws \Magento\Framework\Exception\AlreadyExistsException On unique key conflicts (idempotency key, open slot).
     */
    public function save(PriceMatchRequestInterface $request): PriceMatchRequestInterface;

    /**
     * @param int $requestId
     * @return \MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $requestId): PriceMatchRequestInterface;

    /**
     * Load a request only if it belongs to the given customer.
     *
     * @param int $requestId
     * @param int $customerId
     * @return \MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface
     * @throws NoSuchEntityException
     */
    public function getForCustomer(int $requestId, int $customerId): PriceMatchRequestInterface;

    /**
     * @param string $couponCode
     * @return \MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface|null
     */
    public function findByCouponCode(string $couponCode): ?PriceMatchRequestInterface;

    /**
     * @param int $customerId
     * @param string $idempotencyKey
     * @return \MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface|null
     */
    public function findByIdempotencyKey(int $customerId, string $idempotencyKey): ?PriceMatchRequestInterface;

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): PriceMatchRequestSearchResultsInterface;
}
