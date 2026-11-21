<?php

namespace App\Livewire\Pages;

use App\Livewire\Crud\ResourcePage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;

class Products extends ResourcePage
{
    protected function model(): string
    {
        return Product::class;
    }

    protected function title(): string
    {
        return 'Products';
    }

    protected function banner(): ?string
    {
        return 'site/produce-stall';
    }

    protected function module(): string
    {
        return 'products';
    }

    protected function description(): string
    {
        return 'The commodity catalogue: pricing, units, stock thresholds and storage requirements.';
    }

    protected function query(): Builder
    {
        return Product::query()->with('category')->withSum('batches as stock_on_hand', 'quantity');
    }

    protected function searchable(): array
    {
        return ['name', 'code'];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Product', 'value' => fn ($r) => $r->name],
            ['label' => 'Code', 'value' => fn ($r) => $r->code],
            ['label' => 'Category', 'value' => fn ($r) => $r->category?->name],
            ['label' => 'Unit', 'value' => fn ($r) => $r->unit],
            ['label' => 'Buying price', 'value' => fn ($r) => Options::money($r->buying_price)],
            ['label' => 'Selling price', 'value' => fn ($r) => Options::money($r->selling_price)],
            ['label' => 'In stock', 'value' => fn ($r) => Options::qty($r->stock_on_hand).' / min '.Options::qty($r->min_stock_level)],
            ['label' => 'Type', 'value' => fn ($r) => $r->is_perishable ? 'Perishable' : 'Non-perishable'],
            ['label' => 'Status', 'value' => fn ($r) => $r->status, 'badge' => true],
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            'product_category_id' => ['label' => 'Category', 'options' => ProductCategory::orderBy('name')->pluck('name', 'id')->all()],
            'is_perishable' => ['label' => 'Type', 'options' => ['1' => 'Perishable', '0' => 'Non-perishable']],
            'status' => ['label' => 'Status', 'options' => Options::STATUS],
        ];
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Product name', 'required' => true, 'rules' => ['required', 'string', 'max:255']],
            'product_category_id' => ['label' => 'Category', 'type' => 'select', 'required' => true, 'options' => ProductCategory::where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['required', 'exists:product_categories,id']],
            'unit' => ['label' => 'Unit of measurement', 'type' => 'select', 'required' => true, 'default' => 'kg', 'options' => Options::UNITS, 'rules' => ['required', 'in:'.implode(',', array_keys(Options::UNITS))]],
            'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'default' => 'active', 'options' => Options::STATUS, 'rules' => ['required', 'in:active,inactive']],
            'buying_price' => ['label' => 'Default buying price (KES)', 'type' => 'number', 'required' => true, 'default' => 0, 'rules' => ['required', 'numeric', 'min:0']],
            'selling_price' => ['label' => 'Selling price (KES)', 'type' => 'number', 'required' => true, 'default' => 0, 'rules' => ['required', 'numeric', 'min:0']],
            'min_stock_level' => ['label' => 'Minimum stock level', 'type' => 'number', 'default' => 0, 'rules' => ['required', 'numeric', 'min:0']],
            'min_order_qty' => ['label' => 'Minimum order quantity', 'type' => 'number', 'default' => 1, 'rules' => ['required', 'numeric', 'min:0.01']],
            'is_perishable' => ['label' => 'Perishable', 'type' => 'switch', 'default' => true, 'rules' => ['boolean']],
            'shelf_life_days' => ['label' => 'Shelf life (days)', 'type' => 'number', 'step' => '1', 'help' => 'Sets the expiry date of new batches of perishable produce.', 'rules' => ['nullable', 'integer', 'min:1', 'max:3650']],
            'storage_requirements' => ['label' => 'Storage requirements', 'wide' => true, 'rules' => ['nullable', 'string', 'max:255']],
            'image_path' => ['label' => 'Product image', 'type' => 'image', 'directory' => 'products'],
            'description' => ['label' => 'Description', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000']],
            'slug' => ['label' => 'Web address (URL slug)', 'help' => 'Leave blank to generate from the name. Used in /produce/<slug>.', 'rules' => fn ($record) => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:products,slug,'.($record?->id ?? 'NULL')]],
            'meta_title' => ['label' => 'SEO title (optional)', 'help' => 'Shown in search results. Blank = automatic.', 'rules' => ['nullable', 'string', 'max:120']],
            'meta_description' => ['label' => 'SEO description (optional)', 'type' => 'textarea', 'help' => 'Aim for 70–160 characters. Blank = generated from the description.', 'rules' => ['nullable', 'string', 'max:320']],
        ];
    }

    protected function headerLinks(): array
    {
        return [['Categories', route('products.categories'), 'tag']];
    }
}
