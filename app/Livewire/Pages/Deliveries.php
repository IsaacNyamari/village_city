<?php

namespace App\Livewire\Pages;

use App\Enums\DeliveryStatus;
use App\Livewire\Concerns\Page;
use App\Models\Delivery;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Deliveries extends Component
{
    use Page, WithPagination;

    public string $search = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        $this->guard('logistics.view');
    }

    public function updated(string $name): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $rows = Delivery::with(['order.customer', 'vehicle', 'dispatch'])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$this->search}%")
                ->orWhere('destination', 'like', "%{$this->search}%")
                ->orWhereHas('order', fn ($o) => $o->where('reference', 'like', "%{$this->search}%"))))
            ->when($this->status === 'active', fn ($q) => $q->whereIn('status', DeliveryStatus::activeValues()))
            ->when($this->status !== '' && $this->status !== 'active', fn ($q) => $q->where('status', $this->status))
            ->latest('id')->paginate(15);

        return $this->page('livewire.pages.deliveries', [
            'rows' => $rows,
            'statuses' => DeliveryStatus::cases(),
            'counts' => [
                'active' => Delivery::whereIn('status', DeliveryStatus::activeValues())->count(),
                'delivered' => Delivery::where('status', 'delivered')->count(),
                'failed' => Delivery::whereIn('status', ['failed', 'returned'])->count(),
                'late' => Delivery::whereIn('status', DeliveryStatus::activeValues())->whereNotNull('expected_at')->where('expected_at', '<', now())->count(),
            ],
        ], 'Deliveries');
    }
}
