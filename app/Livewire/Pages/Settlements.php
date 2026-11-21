<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\Procurement;
use App\Models\SupplierSettlement;
use App\Services\FinanceService;
use App\Services\Metrics;
use App\Support\Options;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Settlements extends Component
{
    use Page, WithPagination;

    #[Url]
    public ?int $pay = null;

    public string $tab = 'owed';

    public string $search = '';

    public bool $showForm = false;

    public array $form = [];

    public ?int $reversingId = null;

    public string $reason = '';

    public function mount(): void
    {
        $this->guard('settlements.view');

        if ($this->pay && auth()->user()->can('settlements.manage')) {
            $this->openPay($this->pay);
        }
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['tab', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function openPay(int $procurementId): void
    {
        $this->guard('settlements.manage');
        $procurement = Procurement::where('status', 'inspected')->find($procurementId);

        if (! $procurement || $procurement->balance() <= 0) {
            $this->toast('Nothing is outstanding on this procurement.', 'warning');

            return;
        }

        $this->resetValidation();
        $this->form = ['procurement_id' => $procurementId, 'amount' => (string) $procurement->balance(), 'method' => 'mpesa', 'transaction_ref' => '', 'paid_at' => today()->format('Y-m-d'), 'notes' => ''];
        $this->showForm = true;
    }

    public function save(FinanceService $finance): void
    {
        $this->guard('settlements.manage');
        $this->validate([
            'form.amount' => ['required', 'numeric', 'min:0.01'],
            'form.method' => ['required', 'in:'.implode(',', array_keys(Options::PAYMENT_METHODS))],
            'form.transaction_ref' => ['nullable', 'string', 'max:100'],
            'form.paid_at' => ['required', 'date', 'before_or_equal:today'],
            'form.notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->attempt(fn () => $finance->settle(Procurement::findOrFail($this->form['procurement_id']), (float) $this->form['amount'], $this->form['method'], $this->form['transaction_ref'] ?: null, $this->form['paid_at'], $this->form['notes'] ?: null), 'Settlement recorded.')) {
            $this->showForm = false;
            $this->pay = null;
        }
    }

    public function askReverse(int $id): void
    {
        $this->guard('settlements.manage');
        $this->reversingId = $id;
        $this->reason = '';
    }

    public function reverse(FinanceService $finance): void
    {
        $this->guard('settlements.manage');
        $this->validate(['reason' => ['required', 'string', 'max:255']]);

        if ($this->attempt(fn () => $finance->reverseSettlement(SupplierSettlement::findOrFail($this->reversingId), $this->reason), 'Settlement reversed. The liability has been restored.')) {
            $this->reversingId = null;
        }
    }

    public function render()
    {
        $owed = Procurement::with(['farmer', 'supplier', 'centre'])->where('status', 'inspected')->whereColumn('amount_paid', '<', 'total_amount')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$this->search}%")
                ->orWhereHas('farmer', fn ($f) => $f->where('name', 'like', "%{$this->search}%"))->orWhereHas('supplier', fn ($s) => $s->where('business_name', 'like', "%{$this->search}%"))))
            ->oldest('procurement_date')->paginate(15, pageName: 'owedPage');

        $history = SupplierSettlement::with(['procurement', 'farmer', 'supplier', 'payer'])
            ->when($this->search !== '', fn ($q) => $q->where('reference', 'like', "%{$this->search}%"))
            ->latest('id')->paginate(15, pageName: 'historyPage');

        return $this->page('livewire.pages.settlements', [
            'owed' => $owed, 'history' => $history,
            'liabilities' => app(Metrics::class)->supplierLiabilities(),
            'paidMonth' => (float) SupplierSettlement::where('status', 'completed')->whereDate('paid_at', '>=', now()->startOfMonth())->sum('amount'),
            'target' => $this->showForm ? Procurement::with(['farmer', 'supplier'])->find($this->form['procurement_id']) : null,
        ], 'Supplier Settlements');
    }
}
