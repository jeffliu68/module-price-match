<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Cron;

use Magento\Framework\Stdlib\DateTime\DateTime;
use MagentoGuy\PriceMatch\Model\Queue\EvaluationPublisher;
use MagentoGuy\PriceMatch\Model\ResourceModel\PriceMatchRequest as RequestResource;
use Psr\Log\LoggerInterface;

/**
 * Two jobs in one sweep: fires scheduled retries whose backoff has elapsed, and acts as a lightweight
 * outbox for rows whose original publish was lost. Duplicate publishes are harmless (consumer lease).
 */
class RequeueDueRequests
{
    private const BATCH = 500;
    private const LOST_PUBLISH_GRACE_SECONDS = 300;

    /**
     * @param RequestResource $resource
     * @param EvaluationPublisher $publisher
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RequestResource $resource,
        private readonly EvaluationPublisher $publisher,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $now = $this->dateTime->gmtTimestamp();
        $ids = $this->resource->getDuePendingIds(
            gmdate('Y-m-d H:i:s', $now),
            gmdate('Y-m-d H:i:s', $now - self::LOST_PUBLISH_GRACE_SECONDS),
            self::BATCH
        );
        foreach ($ids as $id) {
            $this->publisher->publish($id);
        }
        if ($ids) {
            $this->logger->info('Price match: requeued due requests.', ['count' => count($ids)]);
        }
    }
}
