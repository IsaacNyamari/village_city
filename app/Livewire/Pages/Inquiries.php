<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\ContactMessage;
use Livewire\Component;
use Livewire\WithPagination;

class Inquiries extends Component
{
    use Page, WithPagination;

    public string $filter = 'open';

    public ?int $viewingId = null;

    public function mount(): void
    {
        $this->guard('inquiries.view');
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function toggleHandled(int $id): void
    {
        $this->guard('inquiries.view');
        $message = ContactMessage::findOrFail($id);
        $message->update(['handled_at' => $message->handled_at ? null : now()]);
        $this->toast($message->handled_at ? 'Marked as handled.' : 'Reopened.');
    }

    public function render()
    {
        return $this->page('livewire.pages.inquiries', [
            'rows' => ContactMessage::query()
                ->when($this->filter === 'open', fn ($q) => $q->whereNull('handled_at'))
                ->when($this->filter === 'handled', fn ($q) => $q->whereNotNull('handled_at'))
                ->latest('id')->paginate(15),
            'openCount' => ContactMessage::whereNull('handled_at')->count(),
            'topics' => ContactMessage::TOPICS,
        ], 'Inquiries');
    }
}
