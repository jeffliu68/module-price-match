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
 * sales_model_service_quote_submit_failure
 */
class ReleaseCouponObserver implements ObserverInterface
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
        if ($quote instanceof Quote) {
            $this->redemptionManager->release($quote);
        }
    }
}
