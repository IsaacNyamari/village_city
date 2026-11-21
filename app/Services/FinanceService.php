<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\FinancialTransaction;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Procurement;
use App\Models\SupplierSettlement;
use App\Support\Options;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FinanceService
{
    public function createInvoice(Order $order): Invoice
    {
        if ($existing = $order->invoice()->first()) {
            return $existing;
        }

        $days = Options::PAYMENT_TERM_DAYS[$order->customer->payment_terms] ?? 0;

        return Invoice::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'issue_date' => today(),
            'due_date' => today()->addDays($days),
            'total' => $order->total,
            'amount_paid' => 0,
            'status' => 'unpaid',
        ]);
    }

    /** Keep the invoice in line with the order when totals change (e.g. partial fulfilment). */
    public function syncInvoice(Order $order): void
    {
        if ($invoice = $order->invoice()->first()) {
            $invoice->update(['total' => $order->total, 'status' => $this->paymentStatus((float) $order->total, (float) $invoice->amount_paid)]);
        }

        $order->update(['payment_status' => $this->paymentStatus((float) $order->total, (float) $order->amount_paid)]);
    }

    public function recordPayment(Invoice $invoice, float $amount, string $method, ?string $transactionRef = null, ?string $date = null, ?string $notes = null): Payment
    {
        return DB::transaction(function () use ($invoice, $amount, $method, $transactionRef, $date, $notes) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status === 'void') {
                throw new RuntimeException('This invoice has been voided.');
            }

            if ($amount <= 0 || $amount > $invoice->balance() + 0.001) {
                throw new RuntimeException('Amount must be between 0.01 and the outstanding balance of '.Options::money($invoice->balance()).'.');
            }

            $payment = Payment::create([
                'customer_id' => $invoice->customer_id,
                'invoice_id' => $invoice->id,
                'order_id' => $invoice->order_id,
                'amount' => $amount,
                'method' => $method,
                'transaction_ref' => $transactionRef,
                'paid_at' => $date ?? today(),
                'notes' => $notes,
                'received_by' => auth()->id(),
            ]);

            $this->applyToInvoice($invoice, $amount);
            $this->ledger('in', 'customer_payment', $amount, $method, $payment->reference, $payment, 'Payment for invoice '.$invoice->number, $payment->paid_at);

            Notifier::send('payments.view', 'Customer payment received', Options::money($amount).' received from '.$invoice->customer->name.' for '.$invoice->number.'.', route('payments.index'), 'banknotes');

            return $payment;
        });
    }

    public function reversePayment(Payment $payment, ?string $reason = null): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== 'completed') {
                throw new RuntimeException('This payment has already been reversed.');
            }

            $payment->update(['status' => 'reversed', 'notes' => trim(($payment->notes ? $payment->notes.' | ' : '').'Reversed: '.($reason ?: 'no reason given'))]);

            if ($payment->invoice) {
                $this->applyToInvoice(Invoice::lockForUpdate()->find($payment->invoice_id), -1 * (float) $payment->amount);
            }

            $this->ledger('out', 'customer_payment_reversal', (float) $payment->amount, $payment->method, $payment->reference, $payment, 'Reversal of payment '.$payment->reference);
        });
    }

    protected function applyToInvoice(Invoice $invoice, float $delta): void
    {
        $paid = round((float) $invoice->amount_paid + $delta, 2);
        $invoice->update(['amount_paid' => $paid, 'status' => $this->paymentStatus((float) $invoice->total, $paid)]);

        $order = $invoice->order;
        $orderPaid = round((float) $order->amount_paid + $delta, 2);
        $order->update(['amount_paid' => $orderPaid, 'payment_status' => $this->paymentStatus((float) $order->total, $orderPaid)]);
    }

    public function settle(Procurement $procurement, float $amount, string $method, ?string $transactionRef = null, ?string $date = null, ?string $notes = null): SupplierSettlement
    {
        return DB::transaction(function () use ($procurement, $amount, $method, $transactionRef, $date, $notes) {
            $procurement = Procurement::lockForUpdate()->findOrFail($procurement->id);

            if ($procurement->status->value !== 'inspected') {
                throw new RuntimeException('Only inspected procurements have a confirmed payable amount.');
            }

            if ($amount <= 0 || $amount > $procurement->balance() + 0.001) {
                throw new RuntimeException('Amount must be between 0.01 and the outstanding balance of '.Options::money($procurement->balance()).'.');
            }

            $settlement = SupplierSettlement::create([
                'procurement_id' => $procurement->id,
                'farmer_id' => $procurement->farmer_id,
                'supplier_id' => $procurement->supplier_id,
                'amount' => $amount,
                'method' => $method,
                'transaction_ref' => $transactionRef,
                'paid_at' => $date ?? today(),
                'notes' => $notes,
                'paid_by' => auth()->id(),
            ]);

            $paid = round((float) $procurement->amount_paid + $amount, 2);
            $procurement->update(['amount_paid' => $paid, 'payment_status' => $this->paymentStatus((float) $procurement->total_amount, $paid)]);

            $this->ledger('out', 'supplier_payment', $amount, $method, $settlement->reference, $settlement, 'Settlement for procurement '.$procurement->reference, $settlement->paid_at);

            return $settlement;
        });
    }

    public function reverseSettlement(SupplierSettlement $settlement, ?string $reason = null): void
    {
        DB::transaction(function () use ($settlement, $reason) {
            $settlement = SupplierSettlement::lockForUpdate()->findOrFail($settlement->id);

            if ($settlement->status !== 'completed') {
                throw new RuntimeException('This settlement has already been reversed.');
            }

            $settlement->update(['status' => 'reversed', 'notes' => trim(($settlement->notes ? $settlement->notes.' | ' : '').'Reversed: '.($reason ?: 'no reason given'))]);

            $procurement = Procurement::lockForUpdate()->find($settlement->procurement_id);
            $paid = round((float) $procurement->amount_paid - (float) $settlement->amount, 2);
            $procurement->update(['amount_paid' => $paid, 'payment_status' => $this->paymentStatus((float) $procurement->total_amount, $paid)]);

            $this->ledger('in', 'supplier_payment_reversal', (float) $settlement->amount, $settlement->method, $settlement->reference, $settlement, 'Reversal of settlement '.$settlement->reference);
        });
    }

    public function recordExpense(array $data): Expense
    {
        return DB::transaction(function () use ($data) {
            $expense = Expense::create($data + ['recorded_by' => auth()->id(), 'status' => 'recorded']);

            $this->ledger('out', 'operating_expense', (float) $expense->amount, $expense->payment_method, $expense->reference, $expense, Options::label('EXPENSE_CATEGORIES', $expense->category).': '.$expense->description, $expense->expense_date);

            return $expense;
        });
    }

    public function voidExpense(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            if ($expense->status !== 'recorded') {
                throw new RuntimeException('This expense has already been voided.');
            }

            $expense->update(['status' => 'voided']);
            $this->ledger('in', 'expense_reversal', (float) $expense->amount, $expense->payment_method, $expense->reference, $expense, 'Voided expense '.$expense->reference);
        });
    }

    public function paymentStatus(float $total, float $paid): string
    {
        return match (true) {
            $paid <= 0 => 'unpaid',
            $paid + 0.001 >= $total => 'paid',
            default => 'partial',
        };
    }

    protected function ledger(string $direction, string $category, float $amount, ?string $method, ?string $reference, Model $source, string $description, mixed $date = null): void
    {
        FinancialTransaction::create([
            'transaction_date' => $date ?? today(),
            'direction' => $direction,
            'category' => $category,
            'amount' => $amount,
            'method' => $method,
            'reference' => $reference,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'description' => $description,
            'user_id' => auth()->id(),
        ]);
    }
}
