<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use MagentoGuy\PriceMatch\Api\Data\PriceMatchRequestInterface as Request;
use MagentoGuy\PriceMatch\Model\Status;

/**
 * Besides CRUD, exposes the atomic, conditional UPDATEs that make the consumer and
 * state transitions safe under concurrency (duplicate deliveries, parallel consumers, admin vs. worker).
 */
class PriceMatchRequest extends AbstractDb
{
    public const TABLE = 'magentoguy_price_match_request';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, Request::REQUEST_ID);
    }

    /**
     * Take a time-boxed lease on a PENDING request that is due. Only one worker can win.
     *
     * @param int $requestId
     * @param string $now UTC Y-m-d H:i:s
     * @param string $leaseUntil UTC Y-m-d H:i:s
     * @return bool
     */
    public function claimForEvaluation(int $requestId, string $now, string $leaseUntil): bool
    {
        $connection = $this->getConnection();
        $affected = $connection->update(
            $this->getMainTable(),
            [
                Request::ATTEMPTS => new \Zend_Db_Expr(Request::ATTEMPTS . ' + 1'),
                'locked_until' => $leaseUntil,
            ],
            [
                Request::REQUEST_ID . ' = ?' => $requestId,
                Request::STATUS . ' = ?' => Status::PENDING,
                '(locked_until IS NULL OR locked_until < ?)' => $now,
                '(' . Request::NEXT_ATTEMPT_AT . ' IS NULL OR ' . Request::NEXT_ATTEMPT_AT . ' <= ?)' => $now,
            ]
        );
        return $affected === 1;
    }

    /**
     * Compare-and-set on status. Returns false if another actor already moved the request.
     *
     * @param int $requestId
     * @param string[] $fromStatuses
     * @param array $data Columns to write, including the new status.
     * @param array $extraWhere Additional conditions, Zend_Db style.
     * @return bool
     */
    public function transition(int $requestId, array $fromStatuses, array $data, array $extraWhere = []): bool
    {
        $affected = $this->getConnection()->update(
            $this->getMainTable(),
            $data,
            [
                Request::REQUEST_ID . ' = ?' => $requestId,
                Request::STATUS . ' IN (?)' => $fromStatuses,
            ] + $extraWhere
        );
        return $affected === 1;
    }

    /**
     * @param int $customerId
     * @param string $sinceUtc
     * @return int
     */
    public function countCreatedSince(int $customerId, string $sinceUtc): int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['cnt' => new \Zend_Db_Expr('COUNT(*)')])
            ->where(Request::CUSTOMER_ID . ' = ?', $customerId)
            ->where(Request::CREATED_AT . ' >= ?', $sinceUtc);
        return (int)$connection->fetchOne($select);
    }

    /**
     * Per-product cooldown check. Rejected requests don't count, so a shopper can retry straight away with a
     * corrected URL or price; the daily limit still caps how often.
     *
     * @param int $customerId
     * @param string $sku
     * @param string $sinceUtc
     * @return bool
     */
    public function hasRequestForSkuSince(int $customerId, string $sku, string $sinceUtc): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), [Request::REQUEST_ID])
            ->where(Request::CUSTOMER_ID . ' = ?', $customerId)
            ->where(Request::SKU . ' = ?', $sku)
            ->where(Request::STATUS . ' <> ?', Status::REJECTED)
            ->where(Request::CREATED_AT . ' >= ?', $sinceUtc)
            ->limit(1);
        return (bool)$connection->fetchOne($select);
    }

    /**
     * PENDING requests that are due: scheduled retries whose time has come, or rows whose
     * original publish was lost (never claimed and older than the grace period).
     *
     * @param string $nowUtc
     * @param string $staleBeforeUtc
     * @param int $limit
     * @return int[]
     */
    public function getDuePendingIds(string $nowUtc, string $staleBeforeUtc, int $limit): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), [Request::REQUEST_ID])
            ->where(Request::STATUS . ' = ?', Status::PENDING)
            ->where('locked_until IS NULL OR locked_until < ?', $nowUtc)
            ->where(
                sprintf(
                    '(%1$s IS NOT NULL AND %1$s <= %2$s) OR (%1$s IS NULL AND %3$s < %4$s)',
                    Request::NEXT_ATTEMPT_AT,
                    $connection->quote($nowUtc),
                    Request::UPDATED_AT,
                    $connection->quote($staleBeforeUtc)
                )
            )
            ->order(Request::REQUEST_ID . ' ASC')
            ->limit($limit);
        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * REDEEMED reservations that never got an order attached (placement crashed after reserving).
     *
     * @param string $staleBeforeUtc
     * @param int $limit
     * @return int[]
     */
    public function getOrphanedReservationIds(string $staleBeforeUtc, int $limit): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), [Request::REQUEST_ID])
            ->where(Request::STATUS . ' = ?', Status::REDEEMED)
            ->where(Request::ORDER_ID . ' IS NULL')
            ->where(Request::UPDATED_AT . ' < ?', $staleBeforeUtc)
            ->limit($limit);
        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * @param string $nowUtc
     * @param int $limit
     * @return int[]
     */
    public function getExpiredApprovedIds(string $nowUtc, int $limit): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), [Request::REQUEST_ID])
            ->where(Request::STATUS . ' = ?', Status::APPROVED)
            ->where(Request::EXPIRES_AT . ' <= ?', $nowUtc)
            ->limit($limit);
        return array_map('intval', $connection->fetchCol($select));
    }
}
