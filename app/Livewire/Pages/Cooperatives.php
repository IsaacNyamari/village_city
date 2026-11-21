<?php

namespace App\Livewire\Pages;

use App\Support\Options;

class Cooperatives extends Suppliers
{
    protected function types(): array
    {
        return Options::COOPERATIVE_TYPES;
    }

    protected function title(): string
    {
        return 'Cooperatives';
    }

    protected function description(): string
    {
        return 'Farmer groups and cooperatives. Farmers can be linked to a group from their farmer record.';
    }

    protected function columns(): array
    {
        $columns = parent::columns();
        array_splice($columns, 4, 0, [['label' => 'Members', 'value' => fn ($r) => $r->members_count]]);

        return $columns;
    }
}
