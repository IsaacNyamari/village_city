<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Farmer;
use App\Models\Order;
use App\Models\Procurement;
use App\Models\Product;
use App\Models\Supplier;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $term = '';

    public function results(): array
    {
        $term = trim($this->term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.$term.'%';
        $user = auth()->user();
        $groups = [];

        if ($user->can('farmers.view')) {
            $groups['Farmers'] = Farmer::where(fn ($q) => $q->where('name', 'like', $like)->orWhere('number', 'like', $like)->orWhere('phone', 'like', $like))->limit(4)->get()
                ->map(fn ($r) => ['label' => $r->name, 'hint' => $r->number, 'url' => route('farmers.show', $r)])->all();
        }

        if ($user->can('suppliers.view')) {
            $groups['Suppliers'] = Supplier::where(fn ($q) => $q->where('business_name', 'like', $like)->orWhere('number', 'like', $like))->limit(4)->get()
                ->map(fn ($r) => ['label' => $r->business_name, 'hint' => $r->number, 'url' => route('suppliers.show', $r)])->all();
        }

        if ($user->can('products.view')) {
            $groups['Products'] = Product::where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like))->limit(4)->get()
                ->map(fn ($r) => ['label' => $r->name, 'hint' => $r->code, 'url' => route('products.index', ['search' => $r->name])])->all();
        }

        if ($user->can('customers.view')) {
            $groups['Customers'] = Customer::where(fn ($q) => $q->where('name', 'like', $like)->orWhere('number', 'like', $like)->orWhere('phone', 'like', $like))->limit(4)->get()
                ->map(fn ($r) => ['label' => $r->name, 'hint' => $r->number, 'url' => route('customers.show', $r)])->all();
        }

        if ($user->can('orders.view')) {
            $groups['Orders'] = Order::with('customer')->where('reference', 'like', $like)->limit(4)->get()
                ->map(fn ($r) => ['label' => $r->reference, 'hint' => $r->customer->name, 'url' => route('orders.show', $r)])->all();
        }

        if ($user->can('procurement.view')) {
            $groups['Procurement'] = Procurement::where('reference', 'like', $like)->limit(4)->get()
                ->map(fn ($r) => ['label' => $r->reference, 'hint' => $r->sourceName(), 'url' => route('procurement.show', $r)])->all();
        }

        return array_filter($groups);
    }

    public function render()
    {
        return view('livewire.global-search', ['groups' => $this->results()]);
    }
}
