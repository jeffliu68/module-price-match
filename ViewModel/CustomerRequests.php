<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\ViewModel;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface;
use MagentoGuy\PriceMatch\Api\PriceMatchRequestRepositoryInterface;
use MagentoGuy\PriceMatch\Model\Resolver\RequestFormatter;

class CustomerRequests implements ArgumentInterface
{
    private const LIMIT = 50;

    /**
     * @param CustomerSession $customerSession
     * @param PriceMatchRequestRepositoryInterface $repository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param RequestFormatter $formatter
     * @param PriceCurrencyInterface $priceCurrency
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly PriceMatchRequestRepositoryInterface $repository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly RequestFormatter $formatter,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * @return array[] Formatted requests, newest first
     */
    public function getRequests(): array
    {
        $criteria = $this->searchCriteriaBuilder
            ->addFilter(PriceMatchRequestInterface::CUSTOMER_ID, (int)$this->customerSession->getCustomerId())
            ->addSortOrder(
                $this->sortOrderBuilder->setField(PriceMatchRequestInterface::REQUEST_ID)->setDescendingDirection()->create()
            )
            ->setPageSize(self::LIMIT)
            ->create();
        return array_map([$this->formatter, 'format'], array_values($this->repository->getList($criteria)->getItems()));
    }

    /**
     * @param array|null $money
     * @return string
     */
    public function formatMoney(?array $money): string
    {
        return $money === null ? '' : $this->priceCurrency->format($money['value'], false);
    }

    /**
     * Store timezone, labelled (e.g. "CDT") so it is not mistaken for the shopper's own.
     *
     * @param string|null $utc
     * @return string
     */
    public function formatDate(?string $utc): string
    {
        if (!$utc) {
            return '';
        }
        $zone = $this->timezone->date(new \DateTime($utc, new \DateTimeZone('UTC')))->format('T');
        return $this->timezone->formatDateTime($utc, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT) . ' ' . $zone;
    }
}
