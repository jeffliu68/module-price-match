<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Rule\Action\Discount;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\SalesRule\Model\Rule\Action\Discount\AbstractDiscount;
use Magento\SalesRule\Model\Rule\Action\Discount\Data;
use Magento\SalesRule\Model\Rule\Action\Discount\DataFactory;
use Magento\SalesRule\Model\Validator;
use MagentoGuy\PriceMatch\Model\Coupon\AppliedRequestResolver;

/**
 * Simple action "price_match_fixed": the discount amount comes from the approved request behind the
 * coupon, not from the rule. One unit of the matched SKU only.
 *
 * Per unit: min(approved discount, current item price - matched price). If our catalog price has since
 * dropped, the customer gets the smaller difference, never a stacked discount below the matched price.
 */
class PriceMatchFixed extends AbstractDiscount
{
    public const ACTION = 'price_match_fixed';

    /**
     * @param Validator $validator
     * @param DataFactory $discountDataFactory
     * @param PriceCurrencyInterface $priceCurrency
     * @param AppliedRequestResolver $requestResolver
     */
    public function __construct(
        Validator $validator,
        DataFactory $discountDataFactory,
        PriceCurrencyInterface $priceCurrency,
        private readonly AppliedRequestResolver $requestResolver
    ) {
        parent::__construct($validator, $discountDataFactory, $priceCurrency);
    }

    /**
     * @inheritdoc
     */
    public function calculate($rule, $item, $qty)
    {
        /** @var Data $discountData */
        $discountData = $this->discountFactory->create();

        $quote = $item->getQuote();
        $request = $this->requestResolver->resolve($rule, $quote);
        if ($request === null
            || strcasecmp((string)$item->getSku(), $request->getSku()) !== 0
            || !$this->requestResolver->isUsableBy($request, $quote)
        ) {
            return $discountData;
        }

        $baseItemPrice = (float)$this->validator->getItemBasePrice($item);
        $perUnit = min(
            (float)$request->getApprovedDiscount(),
            max(0.0, $baseItemPrice - (float)$request->getMatchedPrice())
        );
        $baseAmount = min(
            $baseItemPrice * $item->getQty() - (float)$item->getBaseDiscountAmount(),
            $perUnit * $qty
        );
        $baseAmount = max(0.0, $baseAmount);

        $discountData->setBaseAmount($baseAmount);
        $discountData->setAmount($this->priceCurrency->convert($baseAmount, $quote->getStore()));
        $discountData->setBaseOriginalAmount($baseAmount);
        $discountData->setOriginalAmount($discountData->getAmount());

        return $discountData;
    }

    /**
     * The match covers a single unit regardless of cart quantity.
     *
     * @inheritdoc
     */
    public function fixQuantity($qty, $rule)
    {
        return min((float)$qty, 1.0);
    }
}
