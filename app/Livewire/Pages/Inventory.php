<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\CollectionCentre;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Services\Metrics;
use App\Support\Options;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Inventory extends Component
{
    use Page, WithPagination;

    #[Url]
    public string $tab = 'stock';

    public string $search = '';

    public string $centre = '';

    public string $type = '';

    public bool $showAction = false;

    public ?int $batchId = null;

    public string $action = 'adjust';

    public array $form = [];

    public function mount(): void
    {
        $this->guard('inventory.view');
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['tab', 'search', 'centre', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function open(int $batchId, string $action): void
    {
        $this->guard('inventory.manage');
        abort_unless(in_array($action, ['adjust', 'writeoff', 'transfer'], true), 404);
        $this->resetValidation();
        $this->batchId = $batchId;
        $this->action = $action;
        $this->form = ['quantity' => '', 'direction' => 'decrease', 'type' => 'damage', 'destination' => '', 'notes' => ''];
        $this->showAction = true;
    }

    public function submit(InventoryService $inventory): void
    {
        $this->guard('inventory.manage');
        $batch = StockBatch::findOrFail($this->batchId);

        $this->validate([
            'form.quantity' => ['required', 'numeric', 'min:0.01'],
            'form.notes' => [$this->action === 'transfer' ? 'nullable' : 'required', 'string', 'max:255'],
            'form.direction' => ['required_if:action,adjust', 'in:increase,decrease'],
            'form.type' => ['required_if:action,writeoff', 'in:'.implode(',', array_keys(Options::WRITE_OFF_TYPES))],
            'form.destination' => [$this->action === 'transfer' ? 'required' : 'nullable', 'exists:collection_centres,id'],
        ], ['form.notes.required' => 'Give a reason so the movement can be audited.'], ['form.quantity' => 'quantity', 'form.notes' => 'reason', 'form.destination' => 'destination']);

        $qty = (float) $this->form['quantity'];

        $done = $this->attempt(fn () => match ($this->action) {
            'adjust' => $inventory->adjust($batch, $this->form['direction'] === 'increase' ? $qty : -$qty, $this->form['notes']),
            'writeoff' => $inventory->writeOff($batch, $qty, $this->form['type'], $this->form['notes']),
            'transfer' => $inventory->transfer($batch, CollectionCentre::findOrFail($this->form['destination']), $qty, $this->form['notes'] ?: null),
        }, 'Stock movement recorded.');

        if ($done) {
            $this->showAction = false;
        }
    }

    public function render()
    {
        $metrics = app(Metrics::class);
        $data = [
            'centres' => CollectionCentre::orderBy('name')->pluck('name', 'id'),
            'stockValue' => $metrics->stockValue(),
            'lowStock' => $metrics->lowStock(),
            'expiring' => StockBatch::where('quantity', '>', 0)->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', today()->addDays(2))->count(),
            'batch' => $this->showAction && $this->batchId ? StockBatch::with(['product', 'centre'])->find($this->batchId) : null,
        ];

        if ($this->tab === 'batches') {
            $data['batches'] = StockBatch::with(['product', 'centre'])->where('quantity', '>', 0)
                ->when($this->centre !== '', fn ($q) => $q->where('collection_centre_id', $this->centre))
                ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('batch_number', 'like', "%{$this->search}%")->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$this->search}%"))))
                ->orderBy('received_at')->orderBy('id')->paginate(15);
        } elseif ($this->tab === 'movements') {
            $data['movements'] = StockMovement::with(['product', 'centre', 'batch', 'user'])
                ->when($this->centre !== '', fn ($q) => $q->where('collection_centre_id', $this->centre))
                ->when($this->type !== '', fn ($q) => $q->where('type', $this->type))
                ->when($this->search !== '', fn ($q) => $q->whereHas('product', fn ($p) => $p->where('name', 'like', "%{$this->search}%")))
                ->latest('id')->paginate(20);
        } else {
            // Stock by product (optionally for one location), split into saleable and expired.
            $batches = StockBatch::where('quantity', '>', 0)
                ->when($this->centre !== '', fn ($q) => $q->where('collection_centre_id', $this->centre))->get()->groupBy('product_id');

            $data['stock'] = Product::with('category')->where('status', 'active')
                ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')->get()->map(function (Product $product) use ($batches) {
                    $rows = $batches[$product->id] ?? collect();
                    $product->saleable = (float) $rows->reject(fn (StockBatch $b) => $b->isExpired())->sum('quantity');
                    $product->expired = (float) $rows->filter(fn (StockBatch $b) => $b->isExpired())->sum('quantity');
                    $product->value = (float) $rows->sum(fn ($b) => $b->quantity * $b->purchase_price);
                    $product->locations = $rows->pluck('collection_centre_id')->unique()->count();

                    return $product;
                });
        }

        return $this->page('livewire.pages.inventory', $data, 'Inventory');
    }
}
