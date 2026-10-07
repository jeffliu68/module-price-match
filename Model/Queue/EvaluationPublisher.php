<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Queue;

use Magento\Framework\MessageQueue\PublisherInterface;

class EvaluationPublisher
{
    public const TOPIC = 'magentoguy.pricematch.request.evaluate';

    /**
     * @param PublisherInterface $publisher
     */
    public function __construct(private readonly PublisherInterface $publisher)
    {
    }

    /**
     * The payload is the request ID only: no PII or prices on the wire; the consumer reloads state.
     *
     * @param int $requestId
     * @return void
     */
    public function publish(int $requestId): void
    {
        $this->publisher->publish(self::TOPIC, (string)$requestId);
    }
}
