<?php

namespace App\Livewire\Pages;

use App\Livewire\Crud\ResourcePage;
use App\Models\FeeSchedule;
use App\Support\Options;

class Fees extends ResourcePage
{
    protected function model(): string
    {
        return FeeSchedule::class;
    }

    protected function title(): string
    {
        return 'Fees & Pay Timing';
    }

    protected function singular(): string
    {
        return 'Fee';
    }

    protected function module(): string
    {
        return 'settlements';
    }

    protected function banner(): ?string
    {
        return 'site/market-crates';
    }

    protected function description(): string
    {
        return 'Wages and fees for inspectors, transporters, last-mile riders and sellers, the platform margin, and when each is paid.';
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Fee', 'value' => fn ($r) => $r->name],
            ['label' => 'Payee', 'value' => fn ($r) => FeeSchedule::PAYEES[$r->payee] ?? $r->payee],
            ['label' => 'Rate', 'value' => fn ($r) => $r->basis === 'percent' ? rtrim(rtrim(number_format($r->amount, 2), '0'), '.').'%' : Options::money($r->amount).($r->basis === 'flat' ? '' : ' '.str_replace('per_', '/', $r->basis))],
            ['label' => 'Paid', 'value' => fn ($r) => $r->pay_timing],
            ['label' => 'Status', 'value' => fn ($r) => $r->status, 'badge' => true],
        ];
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Fee name', 'required' => true, 'rules' => ['required', 'string', 'max:255']],
            'code' => ['label' => 'Code', 'required' => true, 'help' => 'Short key, e.g. inspector_daily.', 'rules' => fn ($record) => ['required', 'alpha_dash', 'max:60', 'unique:fee_schedules,code,'.($record?->id ?? 'NULL')]],
            'payee' => ['label' => 'Paid to', 'type' => 'select', 'required' => true, 'options' => FeeSchedule::PAYEES, 'rules' => ['required', 'in:'.implode(',', array_keys(FeeSchedule::PAYEES))]],
            'basis' => ['label' => 'Charged as', 'type' => 'select', 'required' => true, 'default' => 'flat', 'options' => FeeSchedule::BASIS, 'rules' => ['required', 'in:'.implode(',', array_keys(FeeSchedule::BASIS))]],
            'amount' => ['label' => 'Amount (KES or %)', 'type' => 'number', 'required' => true, 'default' => 0, 'rules' => ['required', 'numeric', 'min:0']],
            'pay_timing' => ['label' => 'When it is paid', 'wide' => true, 'rules' => ['nullable', 'string', 'max:255']],
            'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'default' => 'active', 'options' => Options::STATUS, 'rules' => ['required', 'in:active,inactive']],
        ];
    }
}
