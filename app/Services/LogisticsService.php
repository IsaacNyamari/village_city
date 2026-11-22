<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\Delivery;
use App\Models\Dispatch;
use App\Models\Order;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LogisticsService
{
    public function __construct(protected OrderService $orders, protected FinanceService $finance) {}

    /** @param  list<int>  $orderIds */
    public function createDispatch(array $data, array $orderIds): Dispatch
    {
        if ($orderIds === []) {
            throw new RuntimeException('Select at least one order that is ready for dispatch.');
        }

        return DB::transaction(function () use ($data, $orderIds) {
            $orders = Order::whereIn('id', $orderIds)->lockForUpdate()->get();

            foreach ($orders as $order) {
                if ($order->status !== OrderStatus::ReadyForDispatch) {
                    throw new RuntimeException('Order '.$order->reference.' is not ready for dispatch.');
                }

                if ($order->deliveries()->whereIn('status', DeliveryStatus::activeValues())->exists()) {
                    throw new RuntimeException('Order '.$order->reference.' is already assigned to an active delivery.');
                }
            }

            $vehicle = ! empty($data['vehicle_id']) ? Vehicle::find($data['vehicle_id']) : null;

            if ($vehicle) {
                $data['transporter_id'] = $data['transporter_id'] ?? $vehicle->transporter_id;
                $data['driver_name'] = ($data['driver_name'] ?? null) ?: $vehicle->driver_name;
                $data['driver_phone'] = ($data['driver_phone'] ?? null) ?: $vehicle->driver_phone;
            }

            $dispatch = Dispatch::create($data + ['status' => 'scheduled', 'created_by' => auth()->id()]);

            foreach ($orders as $order) {
                $delivery = Delivery::create([
                    'dispatch_id' => $dispatch->id,
                    'order_id' => $order->id,
                    'vehicle_id' => $dispatch->vehicle_id,
                    'driver_name' => $dispatch->driver_name,
                    'origin' => $dispatch->centre->name,
                    'destination' => $order->delivery_address ?: $dispatch->destination,
                    'expected_at' => $dispatch->expected_arrival_at,
                    'status' => $vehicle ? DeliveryStatus::Assigned : DeliveryStatus::Scheduled,
                ]);

                $this->event($delivery, 'Delivery created on dispatch '.$dispatch->reference);
            }

            return $dispatch;
        });
    }

    public function depart(Dispatch $dispatch): void
    {
        DB::transaction(function () use ($dispatch) {
            if ($dispatch->status !== 'scheduled') {
                throw new RuntimeException('Only scheduled dispatches can depart.');
            }

            if (! $dispatch->vehicle_id) {
                throw new RuntimeException('Assign a vehicle before dispatching.');
            }

            if (! $dispatch->vehicle->isCertified()) {
                throw new RuntimeException('Vehicle '.$dispatch->vehicle->registration.' is not certified (or its certification has expired).');
            }

            if (! $dispatch->check?->passed()) {
                throw new RuntimeException('The quality inspector must complete the dispatch sign-off before this load can depart.');
            }

            $dispatch->update(['status' => 'dispatched', 'departure_at' => $dispatch->departure_at ?? now()]);
            $dispatch->vehicle?->update(['status' => 'on_trip']);

            foreach ($dispatch->deliveries()->whereIn('status', ['scheduled', 'assigned'])->get() as $delivery) {
                $delivery->update(['status' => DeliveryStatus::Dispatched, 'dispatched_at' => $dispatch->departure_at, 'vehicle_id' => $dispatch->vehicle_id, 'driver_name' => $dispatch->driver_name]);
                $this->event($delivery, 'Left '.$dispatch->centre->name);
                $this->orders->transition($delivery->order, OrderStatus::Dispatched);

                if ($delivery->order->customer->user) {
                    Notifier::send([], 'Your order is on the way', 'Order '.$delivery->order->reference.' has been dispatched.', route('orders.show', $delivery->order), 'truck', null, $delivery->order->customer->user);
                }
            }

            if ((float) $dispatch->transport_cost > 0 && ! $dispatch->expenses()->exists()) {
                $this->finance->recordExpense([
                    'category' => 'transport',
                    'description' => 'Transport cost for dispatch '.$dispatch->reference,
                    'amount' => $dispatch->transport_cost,
                    'expense_date' => today(),
                    'payment_method' => 'cash',
                    'dispatch_id' => $dispatch->id,
                ]);
            }

            // Heads-up for the destination: the hub / centre manager and inspector.
            foreach (array_filter([$dispatch->centre->manager, $dispatch->centre->inspector]) as $person) {
                Notifier::send([], 'Load '.$dispatch->reference.' has left '.$dispatch->centre->name, 'Vehicle '.$dispatch->vehicle->registration.', driver '.$dispatch->driver_name.'. Expected '.($dispatch->expected_arrival_at?->format('d M H:i') ?? 'soon').'.', route('consignments.index'), 'truck', null, $person);
            }

            Notifier::send('logistics.view', 'Dispatch '.$dispatch->reference.' departed', $dispatch->deliveries()->count().' delivery(ies) left '.$dispatch->centre->name.'.', route('dispatches.index'), 'truck');
        });
    }

    public function markInTransit(Dispatch $dispatch): void
    {
        DB::transaction(function () use ($dispatch) {
            if ($dispatch->status !== 'dispatched') {
                throw new RuntimeException('Only dispatched loads can be marked in transit.');
            }

            $dispatch->update(['status' => 'in_transit']);

            foreach ($dispatch->deliveries()->where('status', 'dispatched')->get() as $delivery) {
                $this->updateDelivery($delivery, DeliveryStatus::InTransit);
            }
        });
    }

    public function cancelDispatch(Dispatch $dispatch): void
    {
        DB::transaction(function () use ($dispatch) {
            if ($dispatch->status !== 'scheduled') {
                throw new RuntimeException('Only scheduled dispatches can be cancelled.');
            }

            foreach ($dispatch->deliveries as $delivery) {
                $delivery->update(['status' => DeliveryStatus::Failed, 'notes' => 'Dispatch cancelled before departure.']);
                $this->event($delivery, 'Dispatch cancelled before departure');
            }

            $dispatch->update(['status' => 'cancelled']);
        });
    }

    public function updateDelivery(Delivery $delivery, DeliveryStatus $to, array $data = []): void
    {
        DB::transaction(function () use ($delivery, $to, $data) {
            $delivery = Delivery::lockForUpdate()->findOrFail($delivery->id);

            if (! $delivery->status->canTransitionTo($to)) {
                throw new RuntimeException('A delivery that is "'.$delivery->status->label().'" cannot be moved to "'.$to->label().'".');
            }

            $attributes = array_filter([
                'recipient_name' => $data['recipient_name'] ?? null,
                'recipient_phone' => $data['recipient_phone'] ?? null,
                'pod_path' => $data['pod_path'] ?? null,
                'notes' => $data['notes'] ?? null,
            ], fn ($v) => filled($v));

            if ($to === DeliveryStatus::Delivered) {
                $attributes['delivered_at'] = now();
            }

            $delivery->update(['status' => $to] + $attributes);
            $this->event($delivery, $data['notes'] ?? null);

            $order = $delivery->order;

            match ($to) {
                DeliveryStatus::InTransit => $order->status === OrderStatus::Dispatched ? $this->orders->transition($order, OrderStatus::InTransit) : null,
                DeliveryStatus::Delivered => $this->orders->transition($order, OrderStatus::Delivered, ['delivered_at' => now()]),
                DeliveryStatus::Failed, DeliveryStatus::Returned => in_array($order->status, [OrderStatus::Dispatched, OrderStatus::InTransit], true)
                    ? $this->orders->transition($order, OrderStatus::ReadyForDispatch) : null,
                default => null,
            };

            if ($to === DeliveryStatus::Delivered) {
                Notifier::send(['logistics.view', 'orders.manage'], 'Delivery completed', 'Order '.$order->reference.' was delivered'.($delivery->recipient_name ? ' to '.$delivery->recipient_name : '').'.', route('deliveries.show', $delivery), 'check-circle', null, $order->customer->user);
            }

            if ($to === DeliveryStatus::Failed || $to === DeliveryStatus::Returned) {
                Notifier::send(['logistics.manage', 'orders.manage'], 'Delivery '.$to->label().': '.$delivery->reference, 'Order '.$order->reference.' needs to be rescheduled.', route('deliveries.show', $delivery), 'exclamation-triangle');
            }

            $this->completeDispatchIfDone($delivery->dispatch);
        });
    }

    protected function completeDispatchIfDone(?Dispatch $dispatch): void
    {
        if (! $dispatch || in_array($dispatch->status, ['completed', 'cancelled', 'scheduled'], true)) {
            return;
        }

        if (! $dispatch->deliveries()->whereIn('status', DeliveryStatus::activeValues())->exists()) {
            $dispatch->update(['status' => 'completed']);
            $dispatch->vehicle?->update(['status' => 'available']);
        }
    }

    protected function event(Delivery $delivery, ?string $note = null): void
    {
        $delivery->events()->create(['status' => $delivery->status->value, 'note' => $note, 'user_id' => auth()->id()]);
    }
}
