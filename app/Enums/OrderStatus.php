<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case PendingConfirmation = 'pending_confirmation';
    case Confirmed = 'confirmed';
    case AwaitingProcurement = 'awaiting_procurement';
    case Processing = 'processing';
    case ReadyForDispatch = 'ready_for_dispatch';
    case Dispatched = 'dispatched';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::PendingConfirmation => 'amber',
            self::Confirmed => 'sky',
            self::AwaitingProcurement => 'orange',
            self::Processing => 'indigo',
            self::ReadyForDispatch => 'teal',
            self::Dispatched, self::InTransit => 'blue',
            self::Delivered => 'green',
            self::Cancelled => 'red',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingConfirmation, self::Cancelled],
            self::PendingConfirmation => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Processing, self::AwaitingProcurement, self::Cancelled],
            self::AwaitingProcurement => [self::Processing, self::Cancelled],
            self::Processing => [self::ReadyForDispatch, self::Cancelled],
            self::ReadyForDispatch => [self::Dispatched, self::Cancelled],
            self::Dispatched => [self::InTransit, self::Delivered, self::ReadyForDispatch],
            self::InTransit => [self::Delivered, self::ReadyForDispatch],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /** Statuses that count as realised sales. */
    public static function salesValues(): array
    {
        return ['processing', 'ready_for_dispatch', 'dispatched', 'in_transit', 'delivered'];
    }

    public static function openValues(): array
    {
        return ['pending_confirmation', 'confirmed', 'awaiting_procurement', 'processing', 'ready_for_dispatch', 'dispatched', 'in_transit'];
    }
}
