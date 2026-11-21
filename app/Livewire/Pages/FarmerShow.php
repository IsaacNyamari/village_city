<?php

namespace App\Livewire\Pages;

use App\Models\Farmer;
use App\Models\HarvestSchedule;
use App\Models\Product;
use App\Support\Options;

class FarmerShow extends DetailPage
{
    public Farmer $farmer;

    public bool $showHarvest = false;

    public array $form = [];

    public function mount(Farmer $farmer): void
    {
        $this->farmer = $farmer;
    }

    protected function permission(): string
    {
        return 'farmers.view';
    }

    protected function extraView(): ?string
    {
        return 'livewire.pages.farmer-harvest';
    }

    public function openHarvest(): void
    {
        $this->guard('farmers.manage');
        $this->resetValidation();
        $this->form = ['product_id' => '', 'season' => '', 'expected_harvest_date' => '', 'expected_quantity' => '', 'notes' => ''];
        $this->showHarvest = true;
    }

    public function saveHarvest(): void
    {
        $this->guard('farmers.manage');
        $this->validate([
            'form.product_id' => ['required', 'exists:products,id'],
            'form.season' => ['nullable', 'string', 'max:100'],
            'form.expected_harvest_date' => ['required', 'date'],
            'form.expected_quantity' => ['required', 'numeric', 'min:0.01'],
            'form.notes' => ['nullable', 'string', 'max:255'],
        ], [], ['form.product_id' => 'commodity', 'form.expected_harvest_date' => 'harvest date', 'form.expected_quantity' => 'expected quantity']);

        $this->farmer->harvestSchedules()->create(array_map(fn ($v) => $v === '' ? null : $v, $this->form));
        $this->showHarvest = false;
        $this->toast('Harvest schedule recorded.');
    }

    public function deleteHarvest(int $id): void
    {
        $this->guard('farmers.manage');
        $this->farmer->harvestSchedules()->whereKey($id)->delete();
        $this->toast('Harvest schedule removed.');
    }

    public function render()
    {
        $this->farmer->refresh();
        $this->guard($this->permission());
        $data = $this->data();

        return $this->page('livewire.pages.detail', [
            'd' => $data, 'extra' => $this->extraView(),
            'products' => $this->showHarvest ? Product::where('status', 'active')->orderBy('name')->pluck('name', 'id') : collect(),
            'schedules' => $this->farmer->harvestSchedules()->with('product')->orderBy('expected_harvest_date')->get(),
        ], $data['title']);
    }

    protected function data(): array
    {
        $f = $this->farmer->load(['county', 'cooperative', 'centre', 'products']);
        $procs = $f->procurements()->with(['items.product', 'centre'])->latest('id')->get();
        $inspected = $procs->filter(fn ($p) => $p->status->value === 'inspected');
        $items = $inspected->flatMap->items;
        $received = (float) $items->sum('received_quantity');

        return [
            'title' => $f->name, 'subtitle' => $f->number.' · '.($f->county?->name ?? 'No county'), 'back' => route('farmers.index'), 'status' => $f->status,
            'stats' => [
                'Total supplied' => [Options::qty($items->sum('accepted_quantity')).' (accepted)', 'scale', 'forest', $procs->count().' procurements'],
                'Acceptance rate' => [$received > 0 ? round($items->sum('accepted_quantity') / $received * 100, 1).'%' : '—', 'check-badge', 'forest', 'Quality performance'],
                'Total earned' => [Options::money($inspected->sum('total_amount')), 'banknotes', 'gold'],
                'Unpaid balance' => [Options::money($inspected->sum(fn ($p) => $p->balance())), 'exclamation-circle', 'red'],
            ],
            'details' => [
                'Phone' => $f->phone, 'Email' => $f->email, 'National ID' => $f->national_id, 'County' => $f->county?->name, 'Sub-county' => $f->sub_county, 'Ward' => $f->ward,
                'Village' => $f->village, 'Location' => $f->location, 'GPS' => $f->gps_coordinates, 'Farm size' => $f->farm_size_acres ? Options::qty($f->farm_size_acres).' acres' : null,
                'Group / cooperative' => $f->cooperative?->business_name, 'Aggregation centre' => $f->centre?->name, 'Registered' => $f->registered_at?->format('d M Y'),
                'Commodities' => $f->products->pluck('name')->implode(', '), 'Notes' => $f->notes,
            ],
            'sections' => [[
                'title' => 'Supply & procurement history', 'empty' => 'No procurement recorded for this farmer yet.',
                'columns' => ['Reference', 'Date', 'Centre', 'Commodities', 'Payable (KES)', 'Paid (KES)', 'Status', 'Payment'],
                'rows' => $procs->map(fn ($p) => [['href' => route('procurement.show', $p), 'text' => $p->reference], $p->procurement_date->format('d M Y'), $p->centre->name, $p->items->pluck('product.name')->implode(', '),
                    $p->status->value === 'inspected' ? Options::money($p->total_amount) : '—', Options::money($p->amount_paid), ['status' => $p->status], $p->status->value === 'inspected' ? ['status' => $p->payment_status, 'list' => 'PAYMENT_STATUS'] : '—'])->all(),
            ], [
                'title' => 'Payments received', 'empty' => 'No payments made to this farmer yet.',
                'columns' => ['Reference', 'Date', 'Procurement', 'Method', 'Transaction ref', 'Amount (KES)', 'Status'],
                'rows' => $f->settlements()->with('procurement')->latest('id')->get()->map(fn ($s) => [$s->reference, $s->paid_at->format('d M Y'), $s->procurement->reference, Options::label('PAYMENT_METHODS', $s->method), $s->transaction_ref, Options::money($s->amount), ['status' => $s->status]])->all(),
            ]],
        ];
    }
}
