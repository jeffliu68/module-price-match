<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Controller\Adminhtml\Request;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use MagentoGuy\PriceMatch\Model\Decision\ManualReview;
use MagentoGuy\PriceMatch\Model\Evaluation\RejectReason;

class Reject extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MagentoGuy_PriceMatch::review';

    /**
     * @param Context $context
     * @param ManualReview $manualReview
     */
    public function __construct(
        Context $context,
        private readonly ManualReview $manualReview
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $requestId = (int)$this->getRequest()->getParam('id');
        $note = trim((string)$this->getRequest()->getParam('note'));
        $note = $note === '' ? null : $note;
        $admin = (string)$this->_auth->getUser()->getUserName();
        try {
            $this->manualReview->reject(
                $requestId,
                $admin,
                (string)($this->getRequest()->getParam('reason') ?: RejectReason::REJECTED_BY_ADMIN),
                $note
            );
            $this->messageManager->addSuccessMessage(__('Request #%1 rejected.', $requestId));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        $redirect = $this->resultRedirectFactory->create();
        return $this->getRequest()->getParam('back') === 'view'
            ? $redirect->setPath('*/*/view', ['id' => $requestId])
            : $redirect->setPath('*/*/index');
    }
}
