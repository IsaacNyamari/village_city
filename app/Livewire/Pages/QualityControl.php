<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\Procurement;
use App\Models\QualityInspection;
use App\Services\ProcurementService;
use App\Support\Options;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class QualityControl extends Component
{
    use Page, WithFileUploads, WithPagination;

    #[Url]
    public ?int $inspect = null;

    public string $grade = '';

    public string $search = '';

    public ?int $inspectingId = null;

    public bool $showInspect = false;

    public array $results = [];

    public array $photos = [];

    public function mount(): void
    {
        $this->guard('quality.view');

        if ($this->inspect && auth()->user()->can('quality.manage')) {
            $this->open($this->inspect);
        }
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['grade', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function open(int $id): void
    {
        $this->guard('quality.manage');
        $procurement = Procurement::with('items')->where('status', 'received')->find($id);

        if (! $procurement) {
            $this->toast('This procurement is not awaiting inspection.', 'danger');

            return;
        }

        $this->resetValidation();
        $this->inspectingId = $procurement->id;
        $this->photos = [];
        $this->results = $procurement->items->where('received_quantity', '>', 0)
            ->mapWithKeys(fn ($i) => [$i->id => ['accepted' => (string) (float) $i->received_quantity, 'grade' => 'A', 'comments' => '']])->all();
        $this->showInspect = true;
    }

    public function save(ProcurementService $service): void
    {
        $this->guard('quality.manage');

        $this->validate([
            'results.*.accepted' => ['required', 'numeric', 'min:0'],
            'results.*.grade' => ['required', 'in:'.implode(',', array_keys(Options::GRADES))],
            'results.*.comments' => ['nullable', 'string', 'max:1000'],
            'photos.*' => ['nullable', 'image', 'max:4096'],
        ], [], ['results.*.accepted' => 'accepted quantity', 'results.*.grade' => 'grade', 'photos.*' => 'photo']);

        $results = $this->results;

        foreach ($this->photos as $itemId => $photo) {
            if ($photo && isset($results[$itemId])) {
                $results[$itemId]['image_path'] = $photo->store('inspections', 'public');
            }
        }

        $procurement = Procurement::findOrFail($this->inspectingId);

        if ($this->attempt(fn () => $service->inspect($procurement, $results), 'Inspection saved. Accepted produce has been added to stock.')) {
            $this->showInspect = false;
            $this->inspectingId = null;
            $this->inspect = null;
        }
    }

    public function render()
    {
        return $this->page('livewire.pages.quality-control', [
            'pending' => Procurement::with(['items.product', 'farmer', 'supplier', 'centre'])->where('status', 'received')->oldest('received_at')->get(),
            'inspecting' => $this->inspectingId ? Procurement::with(['items.product', 'centre'])->find($this->inspectingId) : null,
            'history' => QualityInspection::with(['product', 'procurement.farmer', 'procurement.supplier', 'inspector'])
                ->when($this->grade !== '', fn ($q) => $q->where('grade', $this->grade))
                ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->whereHas('product', fn ($p) => $p->where('name', 'like', "%{$this->search}%"))
                    ->orWhereHas('procurement', fn ($p) => $p->where('reference', 'like', "%{$this->search}%"))))
                ->latest('inspected_at')->paginate(15),
            'totals' => QualityInspection::selectRaw('COALESCE(SUM(received_quantity),0) as received, COALESCE(SUM(accepted_quantity),0) as accepted, COALESCE(SUM(rejected_quantity),0) as rejected, COUNT(*) as inspections')->first(),
        ], 'Quality Control');
    }
}
