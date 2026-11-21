<?php

namespace App\Livewire\Pages;

use App\Models\CollectionCentre;
use App\Support\Options;

class CentreShow extends DetailPage
{
    public CollectionCentre $centre;

    public function mount(CollectionCentre $centre): void
    {
        $this->centre = $centre;
    }

    protected function permission(): string
    {
        return 'centres.view';
    }

    protected function data(): array
    {
        $c = $this->centre->load(['county', 'manager', 'officers']);
        $procs = $c->procurements()->where('status', 'inspected')->with('items')->get();
        $items = $procs->flatMap->items;
        $received = (float) $items->sum('received_quantity');
        $batches = $c->batches()->with('product')->where('quantity', '>', 0)->get();
        $today = $c->movements()->whereDate('created_at', today())->where('type', 'receipt')->sum('quantity');

        return [
            'title' => $c->name, 'subtitle' => $c->code.' · '.Options::label('CENTRE_TYPES', $c->type), 'back' => route('centres.index'), 'status' => $c->status,
            'stats' => [
                'Received today' => [Options::qty($today), 'inbox-arrow-down', 'forest', 'Accepted into stock'],
                'Stock on hand' => [Options::qty($batches->sum('quantity')), 'archive-box', 'blue', Options::money($batches->sum(fn ($b) => $b->quantity * $b->purchase_price)).' at cost'],
                'Acceptance rate' => [$received > 0 ? round($items->sum('accepted_quantity') / $received * 100, 1).'%' : '—', 'check-badge'],
                'Capacity used' => [$c->capacity_kg > 0 ? round($batches->sum('quantity') / $c->capacity_kg * 100, 1).'%' : '—', 'chart-pie', 'gold', $c->capacity_kg ? 'of '.Options::qty($c->capacity_kg).' kg' : 'No capacity set'],
            ],
            'details' => [
                'County' => $c->county?->name, 'Sub-county' => $c->sub_county, 'Ward' => $c->ward, 'Location' => $c->location, 'Manager' => $c->manager?->name,
                'Contact' => $c->contact_phone, 'Officers' => $c->officers->pluck('name')->implode(', '), 'Farmers assigned' => $c->farmers()->count(),
            ],
            'sections' => [[
                'title' => 'Stock at this location', 'empty' => 'No stock on hand.',
                'columns' => ['Product', 'Batch', 'Received', 'Expiry', 'Grade', 'Quantity'],
                'rows' => $batches->map(fn ($b) => [$b->product->name, $b->batch_number, $b->received_at->format('d M Y'), $b->expiry_date?->format('d M Y'), ['status' => $b->grade], Options::qty($b->quantity).' '.$b->product->unit])->all(),
            ], [
                'title' => 'Assigned farmers', 'empty' => 'No farmers assigned to this centre.',
                'columns' => ['Farmer', 'Number', 'Phone', 'Village'],
                'rows' => $c->farmers()->orderBy('name')->limit(50)->get()->map(fn ($f) => [['href' => route('farmers.show', $f), 'text' => $f->name], $f->number, $f->phone, $f->village])->all(),
            ], [
                'title' => 'Recent stock movements', 'empty' => 'No stock movements yet.',
                'columns' => ['Date', 'Type', 'Product', 'Quantity', 'Notes'],
                'rows' => $c->movements()->with('product')->latest('id')->limit(15)->get()->map(fn ($m) => [$m->created_at->format('d M H:i'), ['status' => $m->type, 'list' => 'MOVEMENT_TYPES'], $m->product->name, ($m->quantity > 0 ? '+' : '').Options::qty($m->quantity), $m->notes])->all(),
            ], [
                'title' => 'Dispatches from this centre', 'empty' => 'No dispatches yet.',
                'columns' => ['Reference', 'Destination', 'Departure', 'Status'],
                'rows' => $c->dispatches()->latest('id')->limit(10)->get()->map(fn ($d) => [$d->reference, $d->destination, $d->departure_at?->format('d M H:i'), ['status' => $d->status, 'list' => 'DISPATCH_STATUS']])->all(),
            ]],
        ];
    }
}
