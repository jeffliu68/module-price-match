<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Evaluation;

/**
 * Pure policy: no I/O, so it is trivially unit-testable and reusable by the admin override path.
 */
class RulesEngine
{
    private const EPSILON = 0.00001;

    /**
     * @param EvaluationInput $input
     * @return Decision
     */
    public function evaluate(EvaluationInput $input): Decision
    {
        if (!$input->verified) {
            return Decision::reject(RejectReason::NOT_VERIFIED);
        }
        return $this->evaluatePrice($input->verifiedPrice ?? $input->claimedPrice, $input);
    }

    /**
     * Price checks only; used directly when an admin overrides the authority.
     *
     * @param float $matchedPrice
     * @param EvaluationInput $input
     * @param bool $enforceCap
     * @return Decision
     */
    public function evaluatePrice(float $matchedPrice, EvaluationInput $input, bool $enforceCap = true): Decision
    {
        $matchedPrice = round($matchedPrice, 2);
        $current = round($input->currentPrice, 2);

        if ($matchedPrice <= 0 || $matchedPrice >= $current) {
            return Decision::reject(RejectReason::NOT_LOWER, $matchedPrice);
        }

        $discount = round($current - $matchedPrice, 2);
        if ($enforceCap && ($discount / $current) * 100 > $input->maxDiscountPercent + self::EPSILON) {
            return Decision::reject(RejectReason::EXCEEDS_CAP, $matchedPrice);
        }

        if ($input->floorPrice !== null && $matchedPrice < $input->floorPrice - self::EPSILON) {
            return Decision::reject(RejectReason::BELOW_FLOOR, $matchedPrice);
        }

        return Decision::approve($matchedPrice, $discount);
    }
}
