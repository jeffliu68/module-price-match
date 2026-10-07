<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Ui\Component\Listing\Column;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use MagentoGuy\PriceMatch\Model\Status;

/**
 * View for every row; Approve / Reject only for reviewable rows and only for admins with the review ACL.
 * Actions are POSTed (form key included by Magento_Ui).
 */
class RequestActions extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param AuthorizationInterface $authorization
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorizationInterface $authorization,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritdoc
     */
    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $canReview = $this->authorization->isAllowed('MagentoGuy_PriceMatch::review');
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $id = (int)$item['request_id'];
            $item[$name] = [
                'view' => [
                    'href' => $this->urlBuilder->getUrl('pricematch/request/view', ['id' => $id]),
                    'label' => __('View'),
                ],
            ];
            if (!$canReview || !in_array($item['status'] ?? '', [Status::PENDING, Status::NEEDS_REVIEW], true)) {
                continue;
            }
            $item[$name] += [
                'approve' => [
                    'href' => $this->urlBuilder->getUrl('pricematch/request/approve', ['id' => $id]),
                    'label' => __('Approve'),
                    'post' => true,
                    'confirm' => [
                        'title' => __('Approve request #%1', $id),
                        'message' => __('Issue a single-use coupon for this request? The authority check and discount cap are bypassed; the cost floor still applies.'),
                    ],
                ],
                'reject' => [
                    'href' => $this->urlBuilder->getUrl('pricematch/request/reject', ['id' => $id]),
                    'label' => __('Reject'),
                    'post' => true,
                    'confirm' => [
                        'title' => __('Reject request #%1', $id),
                        'message' => __('Reject this request and notify the customer? To choose a reason or add a note, use View.'),
                    ],
                ],
            ];
        }
        return $dataSource;
    }
}
