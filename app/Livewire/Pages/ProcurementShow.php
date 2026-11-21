<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\Farmer;
use App\Models\Procurement;
use App\Models\Supplier;
use App\Services\ProcurementService;
use Livewire\Component;

class ProcurementShow extends Component
{
    use Page;

    public Procurement $procurement;

    public bool $showReceive = false;

    public array $received = [];

    public bool $showAssign = false;

    public string $sourceType = 'farmer';

    public string $sourceId = '';

    public function mount(Procurement $procurement): void
    {
        $this->guard('procurement.view');
        $this->procurement = $procurement;
    }

    public function openAssign(): void
    {
        $this->guard('procurement.manage');
        $this->sourceType = $this->procurement->supplier_id ? 'supplier' : 'farmer';
        $this->sourceId = (string) ($this->procurement->farmer_id ?? $this->procurement->supplier_id ?? '');
        $this->showAssign = true;
    }

    public function updatedSourceType(): void
    {
        $this->sourceId = '';
    }

    public function assign(): void
    {
        $this->guard('procurement.manage');
        abort_unless($this->procurement->status->value === 'requested', 403);

        $this->validate([
            'sourceType' => ['required', 'in:farmer,supplier'],
            'sourceId' => ['required', $this->sourceType === 'farmer' ? 'exists:farmers,id' : 'exists:suppliers,id'],
        ], [], ['sourceId' => $this->sourceType]);

        $this->procurement->update([
            'farmer_id' => $this->sourceType === 'farmer' ? $this->sourceId : null,
            'supplier_id' => $this->sourceType === 'supplier' ? $this->sourceId : null,
        ]);

        $this->showAssign = false;
        $this->toast('Source assigned.');
    }

    public function confirm(ProcurementService $service): void
    {
        $this->guard('procurement.manage');
        $this->attempt(fn () => $service->confirm($this->procurement), 'Purchase confirmed with the supplier.');
    }

    public function cancel(ProcurementService $service): void
    {
        $this->guard('procurement.manage');
        $this->attempt(fn () => $service->cancel($this->procurement), 'Procurement cancelled.');
    }

    public function openReceive(): void
    {
        $this->guard('procurement.manage');
        $this->resetValidation();
        $this->received = $this->procurement->items->mapWithKeys(fn ($i) => [$i->id => (string) (float) $i->quantity])->all();
        $this->showReceive = true;
    }

    public function receive(ProcurementService $service): void
    {
        $this->guard('procurement.manage');
        $this->validate(['received.*' => ['required', 'numeric', 'min:0']], [], ['received.*' => 'received quantity']);

        if ($this->attempt(fn () => $service->receive($this->procurement, $this->received), 'Produce received. It is now awaiting quality inspection.')) {
            $this->showReceive = false;
        }
    }

    public function render()
    {
        $this->procurement->refresh()->load(['items.product', 'farmer.county', 'supplier.county', 'centre', 'order', 'creator', 'inspections.product', 'inspections.inspector', 'settlements']);

        return $this->page('livewire.pages.procurement-show', [
            'farmers' => $this->showAssign ? Farmer::where('status', 'active')->orderBy('name')->pluck('name', 'id') : collect(),
            'suppliers' => $this->showAssign ? Supplier::where('status', 'active')->orderBy('business_name')->pluck('business_name', 'id') : collect(),
        ], 'Procurement '.$this->procurement->reference);
    }
}
