<?php

namespace App\Livewire\Pages;

use App\Livewire\Crud\ResourcePage;
use App\Models\Expense;
use App\Services\FinanceService;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Expenses extends ResourcePage
{
    protected function model(): string
    {
        return Expense::class;
    }

    protected function title(): string
    {
        return 'Expenses';
    }

    protected function module(): string
    {
        return 'expenses';
    }

    protected function description(): string
    {
        return 'Operating expenses. Recorded expenses are never edited or deleted — void an entry to reverse it in the ledger.';
    }

    protected function query(): Builder
    {
        return Expense::query()->with(['recorder', 'dispatch']);
    }

    protected function searchable(): array
    {
        return ['reference', 'description'];
    }

    protected function deletable(): bool
    {
        return false;
    }

    protected function editable(): bool
    {
        return false;
    }

    protected function hasStatusToggle(): bool
    {
        return false;
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Reference', 'value' => fn ($r) => $r->reference],
            ['label' => 'Date', 'value' => fn ($r) => $r->expense_date->format('d M Y')],
            ['label' => 'Category', 'value' => fn ($r) => Options::label('EXPENSE_CATEGORIES', $r->category)],
            ['label' => 'Description', 'value' => fn ($r) => $r->description, 'class' => 'whitespace-normal'],
            ['label' => 'Amount', 'value' => fn ($r) => Options::money($r->amount)],
            ['label' => 'Method', 'value' => fn ($r) => Options::label('PAYMENT_METHODS', $r->payment_method)],
            ['label' => 'Recorded by', 'value' => fn ($r) => $r->recorder?->name],
            ['label' => 'Status', 'value' => fn ($r) => $r->status, 'badge' => true],
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            'category' => ['label' => 'Category', 'options' => Options::EXPENSE_CATEGORIES],
            'status' => ['label' => 'Status', 'options' => ['recorded' => 'Recorded', 'voided' => 'Voided']],
        ];
    }

    protected function fields(): array
    {
        return [
            'category' => ['label' => 'Category', 'type' => 'select', 'required' => true, 'options' => Options::EXPENSE_CATEGORIES, 'rules' => ['required', 'in:'.implode(',', array_keys(Options::EXPENSE_CATEGORIES))]],
            'expense_date' => ['label' => 'Date', 'type' => 'date', 'required' => true, 'default' => today()->format('Y-m-d'), 'rules' => ['required', 'date', 'before_or_equal:today']],
            'amount' => ['label' => 'Amount (KES)', 'type' => 'number', 'required' => true, 'rules' => ['required', 'numeric', 'min:0.01']],
            'payment_method' => ['label' => 'Payment method', 'type' => 'select', 'required' => true, 'default' => 'cash', 'options' => Options::PAYMENT_METHODS, 'rules' => ['required', 'in:'.implode(',', array_keys(Options::PAYMENT_METHODS))]],
            'description' => ['label' => 'Description', 'required' => true, 'wide' => true, 'rules' => ['required', 'string', 'max:255']],
        ];
    }

    protected function persist(array $data, ?Model $record): Model
    {
        return app(FinanceService::class)->recordExpense($data);
    }

    protected function rowActions(): array
    {
        return [[
            'label' => 'Void expense', 'icon' => 'x-circle', 'method' => 'void',
            'show' => fn ($r) => $r->status === 'recorded',
            'confirm' => 'Void this expense? A reversing entry will be added to the ledger.',
        ]];
    }

    public function void(int $id): void
    {
        $this->guard('expenses.manage');
        $this->attempt(fn () => app(FinanceService::class)->voidExpense(Expense::findOrFail($id)), 'Expense voided.');
    }
}
