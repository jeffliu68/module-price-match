<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Controller\Request;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use MagentoGuy\PriceMatch\Api\SubmitPriceMatchRequestInterface;
use MagentoGuy\PriceMatch\Model\Pricing\ConfigurableChildResolver;
use MagentoGuy\PriceMatch\Model\Resolver\RequestFormatter;
use Psr\Log\LoggerInterface;

/**
 * Luma adapter over the same service the GraphQL mutation uses. Session-authenticated, so it relies on
 * Magento's built-in form key (CSRF) validation for POST actions.
 */
class Submit implements HttpPostActionInterface
{
    /**
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManager
     * @param ProductRepositoryInterface $productRepository
     * @param Configurable $configurableType
     * @param SubmitPriceMatchRequestInterface $submitService
     * @param RequestFormatter $formatter
     * @param LoggerInterface $logger
     * @param ConfigurableChildResolver $childResolver
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly CustomerSession $customerSession,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Configurable $configurableType,
        private readonly SubmitPriceMatchRequestInterface $submitService,
        private readonly RequestFormatter $formatter,
        private readonly LoggerInterface $logger,
        private readonly ConfigurableChildResolver $childResolver
    ) {
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setHttpResponseCode(401)->setData([
                'success' => false,
                'message' => (string)__('Please sign in to request a price match.'),
            ]);
        }

        try {
            $storeId = (int)$this->storeManager->getStore()->getId();
            $request = $this->submitService->execute(
                (int)$this->customerSession->getCustomerId(),
                $storeId,
                $this->resolveSku($storeId),
                (string)$this->request->getParam('competitor_url', ''),
                (float)$this->request->getParam('competitor_price', 0),
                (string)$this->request->getParam('idempotency_key', '')
            );
            return $result->setData(['success' => true, 'request' => $this->formatter->format($request)]);
        } catch (LocalizedException $e) {
            return $result->setHttpResponseCode(422)->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->logger->error('Price match submit failed.', ['exception' => $e]);
            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => (string)__('Something went wrong. Please try again.'),
            ]);
        }
    }

    /**
     * The PDP posts the parent product and, for configurables, the selected child.
     *
     * @param int $storeId
     * @return string
     * @throws LocalizedException
     */
    private function resolveSku(int $storeId): string
    {
        $productId = (int)$this->request->getParam('product_id');
        $childId = (int)$this->request->getParam('child_id');
        try {
            $product = $this->productRepository->getById($productId, false, $storeId);
            if ($product->getTypeId() !== Configurable::TYPE_CODE) {
                return (string)$product->getSku();
            }
            $attributes = array_filter((array)$this->request->getParam('super_attribute', []), 'is_scalar');
            if ($attributes) {
                return $this->childResolver->resolveByAttributes($product, $attributes);
            }
            $parentIds = $childId ? $this->configurableType->getParentIdsByChild($childId) : [];
            if (!in_array($productId, array_map('intval', $parentIds), true)) {
                throw new LocalizedException(__('Please select the product options first.'));
            }
            return (string)$this->productRepository->getById($childId, false, $storeId)->getSku();
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('The requested product is not available.'));
        }
    }
}
