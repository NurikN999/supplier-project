<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PurchaseOrderStatus: string implements HasColor, HasLabel
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

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Placed => 'warning',
            self::Received => 'success',
            self::Cancelled => 'danger',
        };
    }
}
