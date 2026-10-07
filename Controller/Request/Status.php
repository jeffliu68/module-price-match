<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Controller\Request;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use MagentoGuy\PriceMatch\Api\PriceMatchRequestRepositoryInterface;
use MagentoGuy\PriceMatch\Model\Resolver\RequestFormatter;

/**
 * Polling endpoint for the PDP modal. Customer-specific, never cached.
 */
class Status implements HttpGetActionInterface
{
    /**
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     * @param CustomerSession $customerSession
     * @param PriceMatchRequestRepositoryInterface $repository
     * @param RequestFormatter $formatter
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly CustomerSession $customerSession,
        private readonly PriceMatchRequestRepositoryInterface $repository,
        private readonly RequestFormatter $formatter
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
            return $result->setHttpResponseCode(401)->setData(['success' => false]);
        }
        try {
            $request = $this->repository->getForCustomer(
                (int)$this->request->getParam('id'),
                (int)$this->customerSession->getCustomerId()
            );
        } catch (NoSuchEntityException $e) {
            return $result->setHttpResponseCode(404)->setData(['success' => false]);
        }
        return $result->setData(['success' => true, 'request' => $this->formatter->format($request)]);
    }
}
