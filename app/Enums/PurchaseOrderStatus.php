<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Placed = 'placed';
    case Received = 'received';
    case Cancelled = 'cancelled';

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Placed, self::Cancelled],
            self::Placed => [self::Received, self::Cancelled],
            self::Received, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), strict: true);
    }
}
