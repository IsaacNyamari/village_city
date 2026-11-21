<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\FinanceService;
use App\Support\Options;
use Livewire\Component;

class InvoiceShow extends Component
{
    use Page;

    public Invoice $invoice;

    public bool $showPay = false;

    public array $form = [];

    public function mount(Invoice $invoice): void
    {
        $this->guard('invoices.view');
        $this->invoice = $invoice;
    }

    public function openPay(): void
    {
        $this->guard('payments.manage');
        $this->resetValidation();
        $this->form = ['amount' => (string) $this->invoice->balance(), 'method' => 'mpesa', 'transaction_ref' => '', 'paid_at' => today()->format('Y-m-d'), 'notes' => ''];
        $this->showPay = true;
    }

    public function pay(FinanceService $finance): void
    {
        $this->guard('payments.manage');
        $this->validate([
            'form.amount' => ['required', 'numeric', 'min:0.01'],
            'form.method' => ['required', 'in:'.implode(',', array_keys(Options::PAYMENT_METHODS))],
            'form.transaction_ref' => ['nullable', 'string', 'max:100'],
            'form.paid_at' => ['required', 'date', 'before_or_equal:today'],
            'form.notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->attempt(fn () => $finance->recordPayment($this->invoice, (float) $this->form['amount'], $this->form['method'], $this->form['transaction_ref'] ?: null, $this->form['paid_at'], $this->form['notes'] ?: null), 'Payment recorded.')) {
            $this->showPay = false;
        }
    }

    public function render()
    {
        $this->invoice->refresh()->load(['customer', 'order.items.product', 'payments']);

        return $this->page('livewire.pages.invoice-show', ['company' => [
            'name' => Setting::get('company_name'), 'phone' => Setting::get('company_phone'), 'email' => Setting::get('company_email'), 'address' => Setting::get('company_address'),
        ]], 'Invoice '.$this->invoice->number);
    }
}
