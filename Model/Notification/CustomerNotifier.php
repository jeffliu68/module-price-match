<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Notification;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\CustomerNameGenerationInterface;
use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\StoreManagerInterface;
use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface;
use MagentoGuy\PriceMatch\Model\Config;
use MagentoGuy\PriceMatch\Model\Evaluation\RejectReason;
use Psr\Log\LoggerInterface;

/**
 * Best effort: a mail failure never changes the request outcome (status is visible in My Account).
 */
class CustomerNotifier
{
    /**
     * @param Config $config
     * @param TransportBuilder $transportBuilder
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerNameGenerationInterface $nameGeneration
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     * @param PriceCurrencyInterface $priceCurrency
     * @param TimezoneInterface $timezone
     * @param RejectReason $rejectReason
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly TransportBuilder $transportBuilder,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerNameGenerationInterface $nameGeneration,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly TimezoneInterface $timezone,
        private readonly RejectReason $rejectReason,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param PriceMatchRequestInterface $request Reloaded after the decision was committed.
     * @return void
     */
    public function notifyDecision(PriceMatchRequestInterface $request): void
    {
        $approved = $request->getCouponCode() !== null;
        $storeId = $request->getStoreId();
        try {
            $customer = $this->customerRepository->getById($request->getCustomerId());
            $product = $this->productRepository->getById($request->getProductId(), false, $storeId);
            $store = $this->storeManager->getStore($storeId);

            $vars = [
                'customer_name' => $this->nameGeneration->getCustomerName($customer),
                'product_name' => $product->getName(),
                'sku' => $request->getSku(),
                'competitor_host' => $request->getCompetitorHost(),
                'request_id' => $request->getRequestId(),
                'coupon_code' => (string)$request->getCouponCode(),
                'discount' => $this->formatPrice((float)$request->getApprovedDiscount(), $storeId),
                'matched_price' => $this->formatPrice((float)$request->getMatchedPrice(), $storeId),
                'expires_at' => $request->getExpiresAt()
                    ? $this->timezone->formatDateTime($request->getExpiresAt(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT)
                    : '',
                'reason' => $this->rejectReason->getLabel($request->getRejectReason()),
                'account_url' => $store->getUrl('pricematch/request/index'),
            ];

            $this->transportBuilder
                ->setTemplateIdentifier($this->config->getEmailTemplate($approved, $storeId))
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars($vars)
                ->setFromByScope($this->config->getEmailIdentity($storeId), $storeId)
                ->addTo($customer->getEmail(), $vars['customer_name'])
                ->getTransport()
                ->sendMessage();
        } catch (\Throwable $e) {
            $this->logger->error(
                'Price match notification failed.',
                ['request_id' => $request->getRequestId(), 'exception' => $e]
            );
        }
    }

    /**
     * @param float $amount
     * @param int $storeId
     * @return string
     */
    private function formatPrice(float $amount, int $storeId): string
    {
        return $this->priceCurrency->format($amount, false, PriceCurrencyInterface::DEFAULT_PRECISION, $storeId);
    }
}
