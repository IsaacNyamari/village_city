<?php

namespace App\Livewire\Pages;

use App\Models\Supplier;
use App\Support\Options;

class SupplierShow extends DetailPage
{
    public Supplier $supplier;

    public function mount(Supplier $supplier): void
    {
        $this->supplier = $supplier;
    }

    protected function permission(): string
    {
        return 'suppliers.view';
    }

    protected function data(): array
    {
        $s = $this->supplier->load(['county', 'products']);
        $procs = $s->procurements()->with(['items.product', 'centre'])->latest('id')->get();
        $inspected = $procs->filter(fn ($p) => $p->status->value === 'inspected');
        $items = $inspected->flatMap->items;
        $received = (float) $items->sum('received_quantity');
        $ordered = (float) $items->sum('quantity');

        return [
            'title' => $s->business_name, 'subtitle' => $s->number.' · '.Options::label('SUPPLIER_TYPES', $s->type), 'back' => route($s->type === 'individual' || $s->type === 'commercial' ? 'suppliers.index' : 'cooperatives.index'), 'status' => $s->status,
            'stats' => [
                'Procurements' => [$procs->count(), 'clipboard-document-list'],
                'Acceptance rate' => [$received > 0 ? round($items->sum('accepted_quantity') / $received * 100, 1).'%' : '—', 'check-badge', 'forest', 'Quality'],
                'Delivery reliability' => [$ordered > 0 ? round(min(1, $received / $ordered) * 100, 1).'%' : '—', 'truck', 'blue', 'Received vs ordered'],
                'Outstanding' => [Options::money($inspected->sum(fn ($p) => $p->balance())), 'exclamation-circle', 'red'],
            ],
            'details' => [
                'Contact person' => $s->contact_person, 'Phone' => $s->phone, 'Email' => $s->email, 'County' => $s->county?->name, 'Location' => $s->location,
                'Category' => Options::label('SUPPLIER_TYPES', $s->type), 'Payment terms' => Options::label('PAYMENT_TERMS', $s->payment_terms),
                'Members' => in_array($s->type, ['farmer_group', 'cooperative']) ? $s->members()->count().' farmers' : null,
                'Products supplied' => $s->products->pluck('name')->implode(', '), 'Notes' => $s->notes,
            ],
            'sections' => [[
                'title' => 'Transaction history', 'empty' => 'No procurement recorded for this supplier yet.',
                'columns' => ['Reference', 'Date', 'Centre', 'Commodities', 'Payable (KES)', 'Paid (KES)', 'Status', 'Payment'],
                'rows' => $procs->map(fn ($p) => [['href' => route('procurement.show', $p), 'text' => $p->reference], $p->procurement_date->format('d M Y'), $p->centre->name, $p->items->pluck('product.name')->implode(', '),
                    $p->status->value === 'inspected' ? Options::money($p->total_amount) : '—', Options::money($p->amount_paid), ['status' => $p->status], $p->status->value === 'inspected' ? ['status' => $p->payment_status, 'list' => 'PAYMENT_STATUS'] : '—'])->all(),
            ]],
        ];
    }
}
