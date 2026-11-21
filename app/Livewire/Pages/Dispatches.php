<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\CollectionCentre;
use App\Models\DeliveryRoute;
use App\Models\Dispatch;
use App\Models\Order;
use App\Models\Transporter;
use App\Models\Vehicle;
use App\Services\LogisticsService;
use App\Support\Options;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Dispatches extends Component
{
    use Page, WithPagination;

    #[Url]
    public ?int $new = null;

    public string $search = '';

    public string $status = '';

    public bool $showForm = false;

    public array $form = [];

    public array $orderIds = [];

    public function mount(): void
    {
        $this->guard('logistics.view');

        if ($this->new && auth()->user()->can('logistics.manage')) {
            $this->create();
        }
    }

    public function updated(string $name, mixed $value): void
    {
        if (in_array($name, ['search', 'status'], true)) {
            $this->resetPage();
        }

        if ($name === 'form.vehicle_id' && $value && ($vehicle = Vehicle::find($value))) {
            $this->form['transporter_id'] = (string) ($vehicle->transporter_id ?? '');
            $this->form['driver_name'] = (string) $vehicle->driver_name;
            $this->form['driver_phone'] = (string) $vehicle->driver_phone;
        }

        if ($name === 'form.delivery_route_id' && $value && ($route = DeliveryRoute::find($value))) {
            $this->form['destination'] = $route->destination;

            if ($route->estimated_hours && $this->form['departure_at']) {
                $this->form['expected_arrival_at'] = \Illuminate\Support\Carbon::parse($this->form['departure_at'])->addMinutes((int) round($route->estimated_hours * 60))->format('Y-m-d\TH:i');
            }
        }
    }

    public function create(): void
    {
        $this->guard('logistics.manage');
        $this->resetValidation();
        $this->orderIds = [];
        $this->form = [
            'collection_centre_id' => '', 'vehicle_id' => '', 'transporter_id' => '', 'delivery_route_id' => '', 'driver_name' => '', 'driver_phone' => '',
            'destination' => '', 'departure_at' => now()->addHour()->format('Y-m-d\TH:i'), 'expected_arrival_at' => '', 'transport_cost' => '0', 'notes' => '',
        ];
        $this->showForm = true;
    }

    public function save(LogisticsService $logistics): void
    {
        $this->guard('logistics.manage');

        $this->validate([
            'orderIds' => ['required', 'array', 'min:1'],
            'form.collection_centre_id' => ['required', 'exists:collection_centres,id'],
            'form.vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'form.transporter_id' => ['nullable', 'exists:transporters,id'],
            'form.delivery_route_id' => ['nullable', 'exists:delivery_routes,id'],
            'form.driver_name' => ['nullable', 'string', 'max:255'],
            'form.driver_phone' => ['nullable', 'string', 'max:30'],
            'form.destination' => ['required', 'string', 'max:255'],
            'form.departure_at' => ['required', 'date'],
            'form.expected_arrival_at' => ['required', 'date', 'after:form.departure_at'],
            'form.transport_cost' => ['nullable', 'numeric', 'min:0'],
            'form.notes' => ['nullable', 'string', 'max:255'],
        ], ['orderIds.required' => 'Select at least one order.'], [
            'form.collection_centre_id' => 'dispatch location', 'form.destination' => 'destination', 'form.departure_at' => 'departure time',
            'form.expected_arrival_at' => 'expected arrival', 'form.transport_cost' => 'transport cost',
        ]);

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        $data['transport_cost'] = (float) ($data['transport_cost'] ?? 0);

        if ($this->attempt(fn () => $logistics->createDispatch($data, array_map('intval', $this->orderIds)), 'Dispatch scheduled.')) {
            $this->showForm = false;
            $this->new = null;
        }
    }

    public function depart(int $id, LogisticsService $logistics): void
    {
        $this->guard('logistics.manage');
        $this->attempt(fn () => $logistics->depart(Dispatch::findOrFail($id)), 'Dispatch departed. Orders are now marked as dispatched.');
    }

    public function inTransit(int $id, LogisticsService $logistics): void
    {
        $this->guard('logistics.manage');
        $this->attempt(fn () => $logistics->markInTransit(Dispatch::findOrFail($id)), 'Dispatch marked in transit.');
    }

    public function cancel(int $id, LogisticsService $logistics): void
    {
        $this->guard('logistics.manage');
        $this->attempt(fn () => $logistics->cancelDispatch(Dispatch::findOrFail($id)), 'Dispatch cancelled. Its orders are available for a new dispatch.');
    }

    public function render()
    {
        $rows = Dispatch::with(['centre', 'vehicle', 'transporter', 'deliveries.order'])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$this->search}%")->orWhere('destination', 'like', "%{$this->search}%")->orWhere('driver_name', 'like', "%{$this->search}%")))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest('id')->paginate(15);

        $form = $this->showForm;

        return $this->page('livewire.pages.dispatches', [
            'rows' => $rows,
            'readyCount' => Order::where('status', 'ready_for_dispatch')->count(),
            'readyOrders' => $form ? Order::with('customer')->where('status', 'ready_for_dispatch')
                ->whereDoesntHave('deliveries', fn ($q) => $q->whereIn('status', \App\Enums\DeliveryStatus::activeValues()))->oldest('expected_delivery_date')->get() : collect(),
            'centres' => $form ? CollectionCentre::where('status', 'active')->orderBy('name')->pluck('name', 'id') : collect(),
            'vehicles' => $form ? Vehicle::where('status', 'available')->orderBy('registration')->get() : collect(),
            'transporters' => $form ? Transporter::where('status', 'active')->orderBy('name')->pluck('name', 'id') : collect(),
            'routes' => $form ? DeliveryRoute::where('status', 'active')->orderBy('name')->pluck('name', 'id') : collect(),
            'statuses' => Options::DISPATCH_STATUS,
        ], 'Dispatch');
    }
}
