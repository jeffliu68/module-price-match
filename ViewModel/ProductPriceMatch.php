<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use MagentoGuy\PriceMatch\Model\Config;
use MagentoGuy\PriceMatch\Model\Pricing\ProductEligibility;

/**
 * Renders no customer-specific data: the PDP stays full-page cacheable (Varnish). Login state is read
 * client-side from customer-data sections.
 */
class ProductPriceMatch implements ArgumentInterface
{
    private const TYPES = ProductEligibility::OFFERED_TYPES;

    /**
     * @param Config $config
     * @param UrlInterface $urlBuilder
     * @param CustomerUrl $customerUrl
     * @param Json $json
     * @param ProductEligibility $eligibility
     */
    public function __construct(
        private readonly Config $config,
        private readonly UrlInterface $urlBuilder,
        private readonly CustomerUrl $customerUrl,
        private readonly Json $json,
        private readonly ProductEligibility $eligibility
    ) {
    }

    /**
     * @param ProductInterface|null $product
     * @return bool
     */
    public function isAvailable(?ProductInterface $product): bool
    {
        return $product instanceof Product
            && $this->config->isEnabled()
            && in_array($product->getTypeId(), self::TYPES, true)
            && $this->eligibility->isEnabledOnProduct($product);
    }

    /**
     * @param ProductInterface $product
     * @return string
     */
    public function getJsConfig(ProductInterface $product): string
    {
        return $this->json->serialize([
            'productId' => (int)$product->getId(),
            'isConfigurable' => $product->getTypeId() === Configurable::TYPE_CODE,
            'submitUrl' => $this->urlBuilder->getUrl('pricematch/request/submit'),
            'statusUrl' => $this->urlBuilder->getUrl('pricematch/request/status'),
            'accountUrl' => $this->urlBuilder->getUrl('pricematch/request/index'),
            'loginUrl' => $this->customerUrl->getLoginUrl(),
        ]);
    }
}
