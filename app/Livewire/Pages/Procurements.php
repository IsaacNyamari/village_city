<?php

namespace App\Livewire\Pages;

use App\Enums\ProcurementStatus;
use App\Livewire\Concerns\Page;
use App\Models\CollectionCentre;
use App\Models\Farmer;
use App\Models\Procurement;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\ProcurementService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Procurements extends Component
{
    use Page, WithPagination;

    public string $search = '';

    #[Url]
    public string $status = '';

    public string $centre = '';

    public string $payment = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public array $items = [];

    public function mount(): void
    {
        $this->guard('procurement.view');
    }

    public function updated(string $name, mixed $value): void
    {
        if (in_array($name, ['search', 'status', 'centre', 'payment'], true)) {
            $this->resetPage();
        }

        // Default the agreed price to the catalogue buying price when a commodity is chosen.
        if (preg_match('/^items\.(\d+)\.product_id$/', $name, $m) && $value) {
            $this->items[(int) $m[1]]['unit_price'] = (string) (Product::find($value)?->buying_price ?? '');
        }
    }

    public function create(): void
    {
        $this->guard('procurement.manage');
        $this->resetValidation();
        $this->editingId = null;
        $this->form = ['source_type' => 'farmer', 'farmer_id' => '', 'supplier_id' => '', 'collection_centre_id' => '', 'procurement_date' => today()->format('Y-m-d'), 'notes' => ''];
        $this->items = [['product_id' => '', 'quantity' => '', 'unit_price' => '']];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->guard('procurement.manage');
        $this->resetValidation();
        $procurement = Procurement::with('items')->findOrFail($id);

        if ($procurement->status !== ProcurementStatus::Requested) {
            $this->toast('Only requested procurements can be edited.', 'danger');

            return;
        }

        $this->editingId = $procurement->id;
        $this->form = [
            'source_type' => $procurement->supplier_id ? 'supplier' : 'farmer',
            'farmer_id' => (string) $procurement->farmer_id,
            'supplier_id' => (string) $procurement->supplier_id,
            'collection_centre_id' => (string) $procurement->collection_centre_id,
            'procurement_date' => $procurement->procurement_date->format('Y-m-d'),
            'notes' => (string) $procurement->notes,
        ];
        $this->items = $procurement->items->map(fn ($i) => ['product_id' => (string) $i->product_id, 'quantity' => (string) (float) $i->quantity, 'unit_price' => (string) (float) $i->unit_price])->all();
        $this->showForm = true;
    }

    public function addItem(): void
    {
        $this->items[] = ['product_id' => '', 'quantity' => '', 'unit_price' => ''];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(ProcurementService $service): void
    {
        $this->guard('procurement.manage');

        $this->validate([
            'form.source_type' => ['required', 'in:farmer,supplier'],
            'form.farmer_id' => ['nullable', 'exists:farmers,id'],
            'form.supplier_id' => ['nullable', 'exists:suppliers,id'],
            'form.collection_centre_id' => ['required', 'exists:collection_centres,id'],
            'form.procurement_date' => ['required', 'date'],
            'form.notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ], ['items.*.product_id.distinct' => 'Each commodity can only appear once.'], [
            'form.collection_centre_id' => 'collection centre', 'form.procurement_date' => 'procurement date',
            'items.*.product_id' => 'commodity', 'items.*.quantity' => 'quantity', 'items.*.unit_price' => 'unit price',
        ]);

        $data = [
            'farmer_id' => $this->form['source_type'] === 'farmer' ? ($this->form['farmer_id'] ?: null) : null,
            'supplier_id' => $this->form['source_type'] === 'supplier' ? ($this->form['supplier_id'] ?: null) : null,
            'collection_centre_id' => $this->form['collection_centre_id'],
            'procurement_date' => $this->form['procurement_date'],
            'notes' => $this->form['notes'] ?: null,
        ];

        $procurement = $this->editingId ? Procurement::findOrFail($this->editingId) : null;

        if ($this->attempt(fn () => $service->save($data, $this->items, $procurement), 'Procurement '.($procurement ? 'updated.' : 'request created.'))) {
            $this->showForm = false;
        }
    }

    public function render()
    {
        $rows = Procurement::query()
            ->with(['farmer', 'supplier', 'centre', 'items.product', 'order'])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$this->search}%")
                ->orWhereHas('farmer', fn ($f) => $f->where('name', 'like', "%{$this->search}%"))
                ->orWhereHas('supplier', fn ($s) => $s->where('business_name', 'like', "%{$this->search}%"))))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->centre !== '', fn ($q) => $q->where('collection_centre_id', $this->centre))
            ->when($this->payment !== '', fn ($q) => $q->where('status', 'inspected')->where('payment_status', $this->payment))
            ->latest('id')->paginate(15);

        return $this->page('livewire.pages.procurements', [
            'rows' => $rows,
            'centres' => CollectionCentre::where('status', 'active')->orderBy('name')->pluck('name', 'id'),
            'farmers' => Farmer::where('status', 'active')->orderBy('name')->get(['id', 'name', 'number']),
            'suppliers' => Supplier::where('status', 'active')->orderBy('business_name')->get(['id', 'business_name', 'number']),
            'products' => Product::where('status', 'active')->orderBy('name')->get(['id', 'name', 'unit']),
            'statuses' => ProcurementStatus::cases(),
        ], 'Procurement');
    }
}
