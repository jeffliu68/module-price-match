<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Decision;

use Magento\Framework\Stdlib\DateTime\DateTime;
use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface as Request;
use MagentoGuy\PriceMatch\Api\PriceMatchRequestRepositoryInterface;
use MagentoGuy\PriceMatch\Model\Config;
use MagentoGuy\PriceMatch\Model\Coupon\CouponIssuer;
use MagentoGuy\PriceMatch\Model\Notification\CustomerNotifier;
use MagentoGuy\PriceMatch\Model\ResourceModel\History;
use MagentoGuy\PriceMatch\Model\ResourceModel\PriceMatchRequest as RequestResource;
use MagentoGuy\PriceMatch\Model\Status;

/**
 * Every status change goes through here as a compare-and-set on the current status, so the worker,
 * the admin and cron can race without double-approving, double-issuing coupons or resurrecting closed requests.
 * Each method returns false when someone else got there first. Successful changes are written to the history table.
 */
class RequestDecider
{
    /**
     * @param RequestResource $resource
     * @param Status $status
     * @param CouponIssuer $couponIssuer
     * @param CustomerNotifier $notifier
     * @param PriceMatchRequestRepositoryInterface $repository
     * @param Config $config
     * @param DateTime $dateTime
     * @param History $history
     */
    public function __construct(
        private readonly RequestResource $resource,
        private readonly Status $status,
        private readonly CouponIssuer $couponIssuer,
        private readonly CustomerNotifier $notifier,
        private readonly PriceMatchRequestRepositoryInterface $repository,
        private readonly Config $config,
        private readonly DateTime $dateTime,
        private readonly History $history
    ) {
    }

    /**
     * Issue the coupon and approve atomically: if the status CAS fails, the coupon insert is rolled back.
     *
     * @param Request $request
     * @param float $currentPrice
     * @param float|null $verifiedPrice
     * @param float $matchedPrice
     * @param float $discount
     * @param string|null $reviewedBy
     * @param string|null $note
     * @return bool
     * @throws \Exception
     */
    public function approve(
        Request $request,
        float $currentPrice,
        ?float $verifiedPrice,
        float $matchedPrice,
        float $discount,
        ?string $reviewedBy = null,
        ?string $note = null
    ): bool {
        $hours = $this->config->getCouponTtlHours($request->getStoreId());
        $expiresAt = gmdate('Y-m-d H:i:s', $this->dateTime->gmtTimestamp() + $hours * 3600);

        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            $code = $this->couponIssuer->issue($expiresAt);
            $moved = $this->resource->transition(
                (int)$request->getRequestId(),
                $this->status->sourcesFor(Status::APPROVED),
                [
                    Request::STATUS => Status::APPROVED,
                    Request::CURRENT_PRICE => $currentPrice,
                    Request::VERIFIED_PRICE => $verifiedPrice,
                    Request::MATCHED_PRICE => $matchedPrice,
                    Request::APPROVED_DISCOUNT => $discount,
                    Request::COUPON_CODE => $code,
                    Request::EXPIRES_AT => $expiresAt,
                    Request::REJECT_REASON => null,
                    Request::NEXT_ATTEMPT_AT => null,
                    Request::REVIEWED_BY => $reviewedBy,
                    'locked_until' => null,
                ]
            );
            if (!$moved) {
                $connection->rollBack();
                return false;
            }
            $this->history->log(
                (int)$request->getRequestId(),
                History::EVENT_APPROVED,
                Status::APPROVED,
                $reviewedBy,
                $note
            );
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->notifier->notifyDecision($this->repository->getById((int)$request->getRequestId()));
        return true;
    }

    /**
     * @param Request $request
     * @param string $reason
     * @param float|null $currentPrice
     * @param float|null $verifiedPrice
     * @param string|null $reviewedBy
     * @param string|null $note
     * @return bool
     */
    public function reject(
        Request $request,
        string $reason,
        ?float $currentPrice = null,
        ?float $verifiedPrice = null,
        ?string $reviewedBy = null,
        ?string $note = null
    ): bool {
        $data = [
            Request::STATUS => Status::REJECTED,
            Request::REJECT_REASON => $reason,
            Request::OPEN_KEY => null,
            Request::NEXT_ATTEMPT_AT => null,
            Request::REVIEWED_BY => $reviewedBy,
            'locked_until' => null,
        ];
        if ($currentPrice !== null) {
            $data[Request::CURRENT_PRICE] = $currentPrice;
        }
        if ($verifiedPrice !== null) {
            $data[Request::VERIFIED_PRICE] = $verifiedPrice;
        }
        $moved = $this->resource->transition(
            (int)$request->getRequestId(),
            $this->status->sourcesFor(Status::REJECTED),
            $data
        );
        if ($moved) {
            $this->history->log(
                (int)$request->getRequestId(),
                History::EVENT_REJECTED,
                Status::REJECTED,
                $reviewedBy,
                $note
            );
            $this->notifier->notifyDecision($this->repository->getById((int)$request->getRequestId()));
        }
        return $moved;
    }

    /**
     * Park for a human (our dead-letter state). No customer email; it is still "in review" to them.
     *
     * @param Request $request
     * @param string $error
     * @return bool
     */
    public function sendToReview(Request $request, string $error): bool
    {
        $moved = $this->resource->transition(
            (int)$request->getRequestId(),
            [Status::PENDING],
            [
                Request::STATUS => Status::NEEDS_REVIEW,
                Request::LAST_ERROR => mb_substr($error, 0, 2000),
                Request::NEXT_ATTEMPT_AT => null,
                'locked_until' => null,
            ]
        );
        return $this->logIf($moved, (int)$request->getRequestId(), History::EVENT_NEEDS_REVIEW, Status::NEEDS_REVIEW, $error);
    }

    /**
     * Stay PENDING but release the lease and set when the requeue cron may republish it.
     *
     * @param Request $request
     * @param int $delaySeconds
     * @param string $error
     * @return bool
     */
    public function scheduleRetry(Request $request, int $delaySeconds, string $error): bool
    {
        $moved = $this->resource->transition(
            (int)$request->getRequestId(),
            [Status::PENDING],
            [
                Request::NEXT_ATTEMPT_AT => gmdate('Y-m-d H:i:s', $this->dateTime->gmtTimestamp() + $delaySeconds),
                Request::LAST_ERROR => mb_substr($error, 0, 2000),
                'locked_until' => null,
            ]
        );
        return $this->logIf(
            $moved,
            (int)$request->getRequestId(),
            History::EVENT_RETRY_SCHEDULED,
            null,
            sprintf('Retry in %ds: %s', $delaySeconds, $error)
        );
    }

    /**
     * Claim the coupon for an order that is about to be placed. Exactly one concurrent checkout can win.
     *
     * @param int $requestId
     * @return bool
     */
    public function reserveRedemption(int $requestId): bool
    {
        $moved = $this->resource->transition(
            $requestId,
            $this->status->sourcesFor(Status::REDEEMED),
            [Request::STATUS => Status::REDEEMED, Request::OPEN_KEY => null]
        );
        return $this->logIf($moved, $requestId, History::EVENT_RESERVED, Status::REDEEMED);
    }

    /**
     * Compensation when order placement fails after the reservation. Only touches reservations that never
     * got an order attached, so a completed redemption can't be undone.
     *
     * @param int $requestId
     * @param int $customerId
     * @param string $sku
     * @return bool
     */
    public function releaseRedemption(int $requestId, int $customerId, string $sku): bool
    {
        $moved = $this->resource->transition(
            $requestId,
            [Status::REDEEMED],
            [Request::STATUS => Status::APPROVED, Request::OPEN_KEY => $customerId . ':' . $sku],
            [Request::ORDER_ID . ' IS NULL']
        );
        return $this->logIf($moved, $requestId, History::EVENT_RELEASED, Status::APPROVED, 'Order placement failed');
    }

    /**
     * @param int $requestId
     * @param int $orderId
     * @return bool
     */
    public function attachOrder(int $requestId, int $orderId): bool
    {
        $moved = $this->resource->transition(
            $requestId,
            [Status::REDEEMED],
            [Request::ORDER_ID => $orderId],
            [Request::ORDER_ID . ' IS NULL']
        );
        return $this->logIf($moved, $requestId, History::EVENT_ORDER_ATTACHED, null, 'Order ID ' . $orderId);
    }

    /**
     * @param int $requestId
     * @return bool
     */
    public function expire(int $requestId): bool
    {
        $moved = $this->resource->transition(
            $requestId,
            $this->status->sourcesFor(Status::EXPIRED),
            [Request::STATUS => Status::EXPIRED, Request::OPEN_KEY => null]
        );
        return $this->logIf($moved, $requestId, History::EVENT_EXPIRED, Status::EXPIRED);
    }

    /**
     * @param bool $moved
     * @param int $requestId
     * @param string $event
     * @param string|null $status
     * @param string|null $note
     * @return bool $moved, passed through
     */
    private function logIf(bool $moved, int $requestId, string $event, ?string $status, ?string $note = null): bool
    {
        if ($moved) {
            $this->history->log($requestId, $event, $status, null, $note);
        }
        return $moved;
    }
}
