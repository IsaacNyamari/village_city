<?php

namespace App\Enums;

enum ProcurementStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Received = 'received';
    case Inspected = 'inspected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received — Awaiting QC',
            self::Inspected => 'Inspected & Stocked',
            default => ucfirst($this->value),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested => 'amber',
            self::Confirmed => 'sky',
            self::Received => 'indigo',
            self::Inspected => 'green',
            self::Cancelled => 'red',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Requested => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Received, self::Cancelled],
            self::Received => [self::Inspected],
            self::Inspected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}
