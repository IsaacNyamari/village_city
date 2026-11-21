<?php

namespace App\Livewire\Pages;

use App\Livewire\Crud\ResourcePage;
use App\Models\Customer;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;

class Customers extends ResourcePage
{
    protected function model(): string
    {
        return Customer::class;
    }

    protected function title(): string
    {
        return 'Customers';
    }

    protected function banner(): ?string
    {
        return 'site/market-stall';
    }

    protected function module(): string
    {
        return 'customers';
    }

    protected function description(): string
    {
        return 'Wholesale traders, supermarkets, restaurants, institutions and households buying from Village City.';
    }

    protected function query(): Builder
    {
        return Customer::query()->withCount('orders')
            ->withSum(['invoices as outstanding_total' => fn ($q) => $q->where('status', '!=', 'void')], 'total')
            ->withSum(['invoices as outstanding_paid' => fn ($q) => $q->where('status', '!=', 'void')], 'amount_paid');
    }

    protected function searchable(): array
    {
        return ['name', 'number', 'contact_person', 'phone', 'email'];
    }

    protected function showRoute(): ?string
    {
        return 'customers.show';
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Customer', 'value' => fn ($r) => $r->name],
            ['label' => 'Number', 'value' => fn ($r) => $r->number],
            ['label' => 'Type', 'value' => fn ($r) => Options::label('CUSTOMER_TYPES', $r->type)],
            ['label' => 'Phone', 'value' => fn ($r) => $r->phone],
            ['label' => 'Orders', 'value' => fn ($r) => $r->orders_count],
            ['label' => 'Outstanding', 'value' => fn ($r) => Options::money($r->outstanding_total - $r->outstanding_paid)],
            ['label' => 'Credit limit', 'value' => fn ($r) => (float) $r->credit_limit > 0 ? Options::money($r->credit_limit) : 'None'],
            ['label' => 'Status', 'value' => fn ($r) => $r->status, 'badge' => true],
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            'type' => ['label' => 'Type', 'options' => Options::CUSTOMER_TYPES],
            'status' => ['label' => 'Status', 'options' => Options::STATUS],
        ];
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Business / customer name', 'required' => true, 'rules' => ['required', 'string', 'max:255']],
            'type' => ['label' => 'Customer type', 'type' => 'select', 'required' => true, 'default' => 'wholesale', 'options' => Options::CUSTOMER_TYPES, 'rules' => ['required', 'in:'.implode(',', array_keys(Options::CUSTOMER_TYPES))]],
            'contact_person' => ['label' => 'Contact person', 'rules' => ['nullable', 'string', 'max:255']],
            'phone' => ['label' => 'Phone', 'required' => true, 'rules' => ['required', 'string', 'max:30']],
            'email' => ['label' => 'Email', 'type' => 'email', 'rules' => ['nullable', 'email', 'max:255']],
            'county' => ['label' => 'County / city', 'placeholder' => 'Nairobi', 'rules' => ['nullable', 'string', 'max:100']],
            'billing_address' => ['label' => 'Billing address', 'rules' => ['nullable', 'string', 'max:255']],
            'delivery_address' => ['label' => 'Delivery address', 'rules' => ['nullable', 'string', 'max:255']],
            'payment_terms' => ['label' => 'Payment terms', 'type' => 'select', 'required' => true, 'default' => 'cash_on_delivery', 'options' => Options::PAYMENT_TERMS, 'rules' => ['required', 'in:'.implode(',', array_keys(Options::PAYMENT_TERMS))]],
            'credit_limit' => ['label' => 'Credit limit (KES)', 'type' => 'number', 'default' => 0, 'help' => 'Applies to customers on credit terms. 0 means no limit is enforced.', 'rules' => ['required', 'numeric', 'min:0']],
            'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'default' => 'active', 'options' => Options::STATUS, 'rules' => ['required', 'in:active,inactive']],
        ];
    }
}
