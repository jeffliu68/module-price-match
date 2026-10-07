<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Plugin\SalesRule;

use Magento\Quote\Model\Quote\Address;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\ValidateCoupon;
use MagentoGuy\PriceMatch\Model\Coupon\AppliedRequestResolver;

/**
 * Refuse a price match coupon outright (so the cart says "coupon code isn't valid") instead of accepting it
 * with a zero discount, when the quote is not the bound customer, the request is not APPROVED (already
 * redeemed, expired), the TTL passed, or the matched SKU is not in the cart.
 *
 * Also closes the window where core's coupon usage counters lag behind order placement (they are updated
 * asynchronously by the sales.rule.update.coupon.usage consumer): our status flips to REDEEMED synchronously.
 */
class RestrictPriceMatchCouponPlugin
{
    /**
     * @param AppliedRequestResolver $requestResolver
     */
    public function __construct(private readonly AppliedRequestResolver $requestResolver)
    {
    }

    /**
     * @param ValidateCoupon $subject
     * @param bool $result
     * @param Rule $rule
     * @param Address $address
     * @param string|null $couponCode
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        ValidateCoupon $subject,
        bool $result,
        Rule $rule,
        Address $address,
        ?string $couponCode = null
    ): bool {
        if (!$result || !$this->requestResolver->isMasterRule($rule)) {
            return $result;
        }

        $quote = $address->getQuote();
        $request = $this->requestResolver->resolve($rule, $quote);
        if ($request === null || !$this->requestResolver->isUsableBy($request, $quote)) {
            $rule->setIsValidForAddress($address, false);
            return false;
        }
        return true;
    }
}
