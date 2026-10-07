<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Controller\Request;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * My Account > Price Match Requests.
 *
 * Login is checked here rather than via Customer's AccountInterface: that plugin returns null for guests,
 * which only legacy Action subclasses turn into a response; for a plain HttpGetActionInterface controller
 * the front controller throws "Invalid return type" (HTTP 500).
 */
class Index implements HttpGetActionInterface
{
    /**
     * @param PageFactory $pageFactory
     * @param CustomerSession $customerSession
     * @param ResponseInterface $response
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly CustomerSession $customerSession,
        private readonly ResponseInterface $response
    ) {
    }

    /**
     * @return Page|ResponseInterface
     */
    public function execute(): Page|ResponseInterface
    {
        // authenticate() remembers this URL for after login and puts the login redirect on the response.
        if (!$this->customerSession->authenticate()) {
            return $this->response;
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Price Match Requests'));
        return $page;
    }
}
