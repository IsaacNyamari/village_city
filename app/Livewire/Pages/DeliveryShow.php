<?php

namespace App\Livewire\Pages;

use App\Enums\DeliveryStatus;
use App\Livewire\Concerns\Page;
use App\Models\Delivery;
use App\Services\LogisticsService;
use Livewire\Component;
use Livewire\WithFileUploads;

class DeliveryShow extends Component
{
    use Page, WithFileUploads;

    public Delivery $delivery;

    public bool $showUpdate = false;

    public string $to = '';

    public array $form = ['recipient_name' => '', 'recipient_phone' => '', 'notes' => ''];

    public $proof = null;

    public function mount(Delivery $delivery): void
    {
        $this->guard('logistics.view');
        $this->delivery = $delivery;
    }

    public function open(string $to): void
    {
        $this->guard('logistics.manage');
        abort_unless(DeliveryStatus::tryFrom($to) && $this->delivery->status->canTransitionTo(DeliveryStatus::from($to)), 422);
        $this->resetValidation();
        $this->to = $to;
        $this->form = ['recipient_name' => '', 'recipient_phone' => '', 'notes' => ''];
        $this->proof = null;
        $this->showUpdate = true;
    }

    public function save(LogisticsService $logistics): void
    {
        $this->guard('logistics.manage');
        $to = DeliveryStatus::from($this->to);
        $delivered = $to === DeliveryStatus::Delivered;
        $problem = in_array($to, [DeliveryStatus::Failed, DeliveryStatus::Returned], true);

        $this->validate([
            'form.recipient_name' => [$delivered ? 'required' : 'nullable', 'string', 'max:255'],
            'form.recipient_phone' => ['nullable', 'string', 'max:30'],
            'form.notes' => [$problem ? 'required' : 'nullable', 'string', 'max:1000'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ], [], ['form.recipient_name' => 'recipient name', 'form.notes' => $problem ? 'reason' : 'notes', 'proof' => 'proof of delivery']);

        $data = $this->form;

        if ($this->proof) {
            $data['pod_path'] = $this->proof->store('proof-of-delivery', 'public');
        }

        if ($this->attempt(fn () => $logistics->updateDelivery($this->delivery, $to, $data), 'Delivery marked as '.strtolower($to->label()).'.')) {
            $this->showUpdate = false;
        }
    }

    public function render()
    {
        $this->delivery->refresh()->load(['order.customer', 'order.items.product', 'vehicle', 'dispatch.centre', 'dispatch.transporter', 'events.user']);

        return $this->page('livewire.pages.delivery-show', [
            'next' => $this->delivery->dispatch && $this->delivery->dispatch->status === 'scheduled'
                ? [] // status moves with the dispatch until it has departed
                : $this->delivery->status->allowedTransitions(),
        ], 'Delivery '.$this->delivery->reference);
    }
}
