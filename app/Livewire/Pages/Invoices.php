<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\Invoice;
use App\Support\Options;
use Livewire\Component;
use Livewire\WithPagination;

class Invoices extends Component
{
    use Page, WithPagination;

    public string $search = '';

    public string $status = '';

    public bool $overdue = false;

    public function mount(): void
    {
        $this->guard('invoices.view');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $rows = Invoice::with(['customer', 'order'])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$this->search}%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%"))))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->overdue, fn ($q) => $q->whereIn('status', ['unpaid', 'partial'])->whereDate('due_date', '<', today()))
            ->latest('id')->paginate(15);

        return $this->page('livewire.pages.invoices', [
            'rows' => $rows,
            'statuses' => Options::PAYMENT_STATUS + ['void' => 'Void'],
            'outstanding' => (float) Invoice::where('status', '!=', 'void')->selectRaw('COALESCE(SUM(total - amount_paid),0) v')->value('v'),
            'overdueTotal' => (float) Invoice::whereIn('status', ['unpaid', 'partial'])->whereDate('due_date', '<', today())->selectRaw('COALESCE(SUM(total - amount_paid),0) v')->value('v'),
        ], 'Invoices');
    }
}
