<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\Dispatch;
use Livewire\Component;
use Livewire\WithPagination;

/** Read-only tracking board: seller, buyer total, inspector, vehicle and sign-off per consignment. */
class Consignments extends Component
{
    use Page, WithPagination;

    public string $search = '';

    public function mount(): void
    {
        $this->guard('logistics.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $rows = Dispatch::query()
            ->with(['centre.inspector', 'vehicle', 'transporter', 'check.inspector', 'deliveries.order.customer', 'deliveries.order.procurements.farmer'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$this->search}%")
                ->orWhere('driver_name', 'like', "%{$this->search}%")
                ->orWhereHas('vehicle', fn ($v) => $v->where('registration', 'like', "%{$this->search}%"))
                ->orWhereHas('deliveries', fn ($d) => $d->where('parcel_code', 'like', "%{$this->search}%"))))
            ->latest('id')->paginate(10);

        return $this->page('livewire.pages.consignments', ['rows' => $rows], 'Consignment Tracking');
    }
}
