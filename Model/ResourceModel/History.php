<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Append-only audit trail of request events. Written by RequestDecider after each successful
 * transition, so it shares the transition's connection (and transaction, when there is one).
 */
class History extends AbstractDb
{
    public const TABLE = 'magentoguy_price_match_request_history';

    public const EVENT_APPROVED = 'approved';
    public const EVENT_REJECTED = 'rejected';
    public const EVENT_NEEDS_REVIEW = 'needs_review';
    public const EVENT_RETRY_SCHEDULED = 'retry_scheduled';
    public const EVENT_RESERVED = 'reserved';
    public const EVENT_RELEASED = 'released';
    public const EVENT_ORDER_ATTACHED = 'order_attached';
    public const EVENT_EXPIRED = 'expired';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, 'history_id');
    }

    /**
     * @param int $requestId
     * @param string $event
     * @param string|null $status Status after the event, if it changed
     * @param string|null $actor Admin username; null for the system
     * @param string|null $note
     * @return void
     */
    public function log(int $requestId, string $event, ?string $status, ?string $actor = null, ?string $note = null): void
    {
        $this->getConnection()->insert($this->getMainTable(), [
            'request_id' => $requestId,
            'event' => $event,
            'status' => $status,
            'actor' => $actor,
            'note' => $note === null ? null : mb_substr($note, 0, 2000),
        ]);
    }

    /**
     * @param int $requestId
     * @return array[] Oldest first
     */
    public function getByRequestId(int $requestId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('request_id = ?', $requestId)
            ->order(['created_at ASC', 'history_id ASC']);
        return $connection->fetchAll($select);
    }
}
