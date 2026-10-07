<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model\Queue;

use MagentoGuy\PriceMatch\Model\Evaluation\Evaluator;
use Psr\Log\LoggerInterface;

/**
 * Never throws: failures are recorded on the request (retry schedule or NEEDS_REVIEW), so a bad
 * message can't poison the queue or loop on redelivery.
 */
class EvaluationConsumer
{
    /**
     * @param Evaluator $evaluator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Evaluator $evaluator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param string $requestId
     * @return void
     */
    public function process(string $requestId): void
    {
        if (!ctype_digit($requestId)) {
            $this->logger->warning('Price match: dropping malformed message.', ['payload' => $requestId]);
            return;
        }
        try {
            $this->evaluator->evaluate((int)$requestId);
        } catch (\Throwable $e) {
            $this->logger->critical('Price match consumer error.', ['request_id' => $requestId, 'exception' => $e]);
        }
    }
}
