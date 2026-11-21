<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\FinancialTransaction;
use Livewire\Component;
use Livewire\WithPagination;

class Transactions extends Component
{
    use Page, WithPagination;

    public string $direction = '';

    public string $category = '';

    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $this->guard('finance.view');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    protected function base()
    {
        return FinancialTransaction::query()
            ->when($this->direction !== '', fn ($q) => $q->where('direction', $this->direction))
            ->when($this->category !== '', fn ($q) => $q->where('category', $this->category))
            ->when($this->from !== '', fn ($q) => $q->whereDate('transaction_date', '>=', $this->from))
            ->when($this->to !== '', fn ($q) => $q->whereDate('transaction_date', '<=', $this->to));
    }

    public function render()
    {
        return $this->page('livewire.pages.transactions', [
            'rows' => $this->base()->with('user')->latest('transaction_date')->latest('id')->paginate(20),
            'in' => (float) $this->base()->where('direction', 'in')->sum('amount'),
            'out' => (float) $this->base()->where('direction', 'out')->sum('amount'),
            'categories' => FinancialTransaction::CATEGORIES,
        ], 'Financial Transactions');
    }
}
