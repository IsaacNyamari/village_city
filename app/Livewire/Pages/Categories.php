<?php

namespace App\Livewire\Pages;

use App\Livewire\Crud\ResourcePage;
use App\Models\ProductCategory;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;

class Categories extends ResourcePage
{
    protected function model(): string
    {
        return ProductCategory::class;
    }

    protected function title(): string
    {
        return 'Product Categories';
    }

    protected function singular(): string
    {
        return 'Category';
    }

    protected function module(): string
    {
        return 'products';
    }

    protected function description(): string
    {
        return 'Commodity groupings used in the catalogue, marketplace and reports.';
    }

    protected function query(): Builder
    {
        return ProductCategory::query()->withCount('products');
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Category', 'value' => fn ($r) => $r->name],
            ['label' => 'Description', 'value' => fn ($r) => $r->description, 'class' => 'whitespace-normal'],
            ['label' => 'Products', 'value' => fn ($r) => $r->products_count],
            ['label' => 'Status', 'value' => fn ($r) => $r->status, 'badge' => true],
        ];
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Category name', 'required' => true, 'rules' => fn ($record) => ['required', 'string', 'max:255', 'unique:product_categories,name,'.($record?->id ?? 'NULL')]],
            'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'default' => 'active', 'options' => Options::STATUS, 'rules' => ['required', 'in:active,inactive']],
            'description' => ['label' => 'Description', 'wide' => true, 'rules' => ['nullable', 'string', 'max:255']],
            'slug' => ['label' => 'Web address (URL slug)', 'help' => 'Used in /produce/category/<slug>. Blank = generated.', 'rules' => fn ($record) => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:product_categories,slug,'.($record?->id ?? 'NULL')]],
            'meta_title' => ['label' => 'SEO title (optional)', 'rules' => ['nullable', 'string', 'max:120']],
            'meta_description' => ['label' => 'SEO description (optional)', 'wide' => true, 'rules' => ['nullable', 'string', 'max:320']],
            'seo_text' => ['label' => 'Introduction text for the public category page', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000']],
        ];
    }

    protected function headerLinks(): array
    {
        return [['Back to products', route('products.index'), 'arrow-left']];
    }
}
