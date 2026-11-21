<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\FinanceService;
use App\Support\Options;
use Livewire\Component;
use Livewire\WithPagination;

class Payments extends Component
{
    use Page, WithPagination;

    public string $search = '';

    public string $method = '';

    public bool $showForm = false;

    public array $form = [];

    public ?int $reversingId = null;

    public string $reason = '';

    public function mount(): void
    {
        $this->guard('payments.view');
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['search', 'method'], true)) {
            $this->resetPage();
        }

        if ($name === 'form.invoice_id' && $this->form['invoice_id'] && ($invoice = Invoice::find($this->form['invoice_id']))) {
            $this->form['amount'] = (string) $invoice->balance();
        }
    }

    public function create(): void
    {
        $this->guard('payments.manage');
        $this->resetValidation();
        $this->form = ['invoice_id' => '', 'amount' => '', 'method' => 'mpesa', 'transaction_ref' => '', 'paid_at' => today()->format('Y-m-d'), 'notes' => ''];
        $this->showForm = true;
    }

    public function save(FinanceService $finance): void
    {
        $this->guard('payments.manage');
        $this->validate([
            'form.invoice_id' => ['required', 'exists:invoices,id'],
            'form.amount' => ['required', 'numeric', 'min:0.01'],
            'form.method' => ['required', 'in:'.implode(',', array_keys(Options::PAYMENT_METHODS))],
            'form.transaction_ref' => ['nullable', 'string', 'max:100'],
            'form.paid_at' => ['required', 'date', 'before_or_equal:today'],
            'form.notes' => ['nullable', 'string', 'max:255'],
        ], [], ['form.invoice_id' => 'invoice']);

        if ($this->attempt(fn () => $finance->recordPayment(Invoice::findOrFail($this->form['invoice_id']), (float) $this->form['amount'], $this->form['method'], $this->form['transaction_ref'] ?: null, $this->form['paid_at'], $this->form['notes'] ?: null), 'Payment recorded.')) {
            $this->showForm = false;
        }
    }

    public function askReverse(int $id): void
    {
        $this->guard('payments.manage');
        $this->reversingId = $id;
        $this->reason = '';
    }

    public function reverse(FinanceService $finance): void
    {
        $this->guard('payments.manage');
        $this->validate(['reason' => ['required', 'string', 'max:255']]);

        if ($this->attempt(fn () => $finance->reversePayment(Payment::findOrFail($this->reversingId), $this->reason), 'Payment reversed. The invoice balance has been restored.')) {
            $this->reversingId = null;
        }
    }

    public function render()
    {
        $rows = Payment::with(['customer', 'invoice', 'receiver'])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$this->search}%")->orWhere('transaction_ref', 'like', "%{$this->search}%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%"))))
            ->when($this->method !== '', fn ($q) => $q->where('method', $this->method))
            ->latest('id')->paginate(15);

        return $this->page('livewire.pages.payments', [
            'rows' => $rows,
            'openInvoices' => $this->showForm ? Invoice::with('customer')->whereIn('status', ['unpaid', 'partial'])->orderBy('id')->get() : collect(),
            'received' => (float) Payment::where('status', 'completed')->whereDate('paid_at', '>=', now()->startOfMonth())->sum('amount'),
        ], 'Payments');
    }
}
