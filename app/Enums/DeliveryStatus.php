<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Scheduled = 'scheduled';
    case Assigned = 'assigned';
    case Dispatched = 'dispatched';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Returned = 'returned';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'zinc',
            self::Assigned => 'amber',
            self::Dispatched, self::InTransit => 'blue',
            self::Delivered => 'green',
            self::Failed => 'red',
            self::Returned => 'orange',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Scheduled => [self::Assigned, self::Dispatched, self::Failed],
            self::Assigned => [self::Dispatched, self::Failed],
            self::Dispatched => [self::InTransit, self::Delivered, self::Failed, self::Returned],
            self::InTransit => [self::Delivered, self::Failed, self::Returned],
            self::Delivered, self::Failed, self::Returned => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public static function activeValues(): array
    {
        return ['scheduled', 'assigned', 'dispatched', 'in_transit'];
    }
}
