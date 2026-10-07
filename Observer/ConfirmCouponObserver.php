<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use MagentoGuy\PriceMatch\Model\Redemption\RedemptionManager;

/**
 * sales_model_service_quote_submit_success
 */
class ConfirmCouponObserver implements ObserverInterface
{
    /**
     * @param RedemptionManager $redemptionManager
     */
    public function __construct(private readonly RedemptionManager $redemptionManager)
    {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getData('quote');
        $order = $observer->getEvent()->getData('order');
        if ($quote instanceof Quote && $order && $order->getId()) {
            $this->redemptionManager->confirm($quote, (int)$order->getId());
        }
    }
}
