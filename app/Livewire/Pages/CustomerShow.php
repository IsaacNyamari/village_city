<?php

namespace App\Livewire\Pages;

use App\Models\Customer;
use App\Support\Options;

class CustomerShow extends DetailPage
{
    public Customer $customer;

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;
    }

    protected function permission(): string
    {
        return 'customers.view';
    }

    protected function data(): array
    {
        $c = $this->customer;
        $balance = $c->outstandingBalance();
        $limit = (float) $c->credit_limit;

        return [
            'title' => $c->name, 'subtitle' => $c->number.' · '.Options::label('CUSTOMER_TYPES', $c->type), 'back' => route('customers.index'), 'status' => $c->status,
            'stats' => [
                'Orders' => [$c->orders()->count(), 'shopping-cart'],
                'Total purchased' => [Options::money($c->orders()->whereIn('status', \App\Enums\OrderStatus::salesValues())->sum('total')), 'banknotes', 'gold'],
                'Outstanding balance' => [Options::money($balance), 'exclamation-circle', $balance > 0 ? 'red' : 'forest'],
                'Credit available' => [$limit > 0 ? Options::money(max(0, $limit - $c->creditExposure())) : 'No limit set', 'credit-card', 'blue', $limit > 0 ? 'Limit '.Options::money($limit) : null],
            ],
            'details' => [
                'Contact person' => $c->contact_person, 'Phone' => $c->phone, 'Email' => $c->email, 'County / city' => $c->county, 'Billing address' => $c->billing_address,
                'Delivery address' => $c->delivery_address, 'Payment terms' => Options::label('PAYMENT_TERMS', $c->payment_terms), 'Credit limit' => $limit > 0 ? Options::money($limit) : 'None',
                'Online account' => $c->user?->email,
            ],
            'sections' => [[
                'title' => 'Order history', 'empty' => 'No orders yet.', 'columns' => ['Order', 'Date', 'Total (KES)', 'Paid (KES)', 'Status', 'Payment'],
                'rows' => $c->orders()->latest('id')->limit(25)->get()->map(fn ($o) => [['href' => route('orders.show', $o), 'text' => $o->reference], $o->created_at->format('d M Y'), Options::money($o->total), Options::money($o->amount_paid), ['status' => $o->status], ['status' => $o->payment_status, 'list' => 'PAYMENT_STATUS']])->all(),
            ], [
                'title' => 'Invoices', 'empty' => 'No invoices yet.', 'columns' => ['Invoice', 'Issued', 'Due', 'Total (KES)', 'Balance (KES)', 'Status'],
                'rows' => $c->invoices()->latest('id')->limit(25)->get()->map(fn ($i) => [['href' => route('invoices.show', $i), 'text' => $i->number], $i->issue_date->format('d M Y'), $i->due_date?->format('d M Y'), Options::money($i->total), Options::money($i->status === 'void' ? 0 : $i->balance()), ['status' => $i->status, 'list' => 'PAYMENT_STATUS']])->all(),
            ], [
                'title' => 'Payment history', 'empty' => 'No payments yet.', 'columns' => ['Reference', 'Date', 'Method', 'Transaction ref', 'Amount (KES)', 'Status'],
                'rows' => $c->payments()->latest('id')->limit(25)->get()->map(fn ($p) => [$p->reference, $p->paid_at->format('d M Y'), Options::label('PAYMENT_METHODS', $p->method), $p->transaction_ref, Options::money($p->amount), ['status' => $p->status]])->all(),
            ]],
        ];
    }
}
