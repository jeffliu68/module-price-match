<?php
/**
 * Copyright © MagentoGuy. All rights reserved.
 */
declare(strict_types=1);

namespace MagentoGuy\PriceMatch\Model;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Single source of truth for the request state machine.
 */
class Status implements OptionSourceInterface
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const NEEDS_REVIEW = 'needs_review';
    public const REDEEMED = 'redeemed';
    public const EXPIRED = 'expired';

    private const TRANSITIONS = [
        self::PENDING => [self::APPROVED, self::REJECTED, self::NEEDS_REVIEW],
        self::NEEDS_REVIEW => [self::APPROVED, self::REJECTED],
        self::APPROVED => [self::REDEEMED, self::EXPIRED],
        self::REJECTED => [],
        self::REDEEMED => [],
        self::EXPIRED => [],
    ];

    /**
     * Open requests hold the customer+SKU slot (see open_key); closed ones release it.
     */
    private const OPEN = [self::PENDING, self::NEEDS_REVIEW, self::APPROVED];

    /**
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * States from which a transition to $to is allowed.
     *
     * @param string $to
     * @return string[]
     */
    public function sourcesFor(string $to): array
    {
        $sources = [];
        foreach (self::TRANSITIONS as $from => $targets) {
            if (in_array($to, $targets, true)) {
                $sources[] = $from;
            }
        }
        return $sources;
    }

    /**
     * @param string $status
     * @return bool
     */
    public function isOpen(string $status): bool
    {
        return in_array($status, self::OPEN, true);
    }

    /**
     * @return array<string, \Magento\Framework\Phrase>
     */
    public function getLabels(): array
    {
        return [
            self::PENDING => __('Pending'),
            self::APPROVED => __('Approved'),
            self::REJECTED => __('Rejected'),
            self::NEEDS_REVIEW => __('Needs Review'),
            self::REDEEMED => __('Redeemed'),
            self::EXPIRED => __('Expired'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->getLabels() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        return $options;
    }
}
