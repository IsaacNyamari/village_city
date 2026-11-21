<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\AuditLog;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogs extends Component
{
    use Page, WithPagination;

    public string $search = '';

    public string $action = '';

    public string $user = '';

    public function mount(): void
    {
        $this->guard('audit.view');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return $this->page('livewire.pages.audit-logs', [
            'rows' => AuditLog::with('user')
                ->when($this->search !== '', fn ($q) => $q->where('description', 'like', "%{$this->search}%"))
                ->when($this->action !== '', fn ($q) => $q->where('action', $this->action))
                ->when($this->user !== '', fn ($q) => $q->where('user_id', $this->user))
                ->latest('id')->paginate(25),
            'actions' => AuditLog::select('action')->distinct()->orderBy('action')->pluck('action'),
            'users' => User::orderBy('name')->pluck('name', 'id'),
        ], 'Audit Logs');
    }
}
