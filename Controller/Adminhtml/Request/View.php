<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Controller\Adminhtml\Request;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use MagentoGuy\PriceMatch\Api\PriceMatchRequestRepositoryInterface;

/**
 * Detail page with the audit history; approve/reject forms appear for reviewable rows.
 */
class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MagentoGuy_PriceMatch::requests';

    /**
     * @param Context $context
     * @param PriceMatchRequestRepositoryInterface $repository
     */
    public function __construct(
        Context $context,
        private readonly PriceMatchRequestRepositoryInterface $repository
    ) {
        parent::__construct($context);
    }

    /**
     * @return Page|Redirect
     */
    public function execute()
    {
        $requestId = (int)$this->getRequest()->getParam('id');
        try {
            $this->repository->getById($requestId);
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('This price match request no longer exists.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('MagentoGuy_PriceMatch::requests');
        $page->getConfig()->getTitle()->prepend(__('Price Match Request #%1', $requestId));
        return $page;
    }
}
