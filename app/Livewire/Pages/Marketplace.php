<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Services\Metrics;
use App\Services\OrderService;
use App\Support\Cart;
use App\Support\Options;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Marketplace extends Component
{
    use Page, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    /** @var array<int, float|string> product id => quantity */
    public array $cart = [];

    #[Url(as: 'cart')]
    public bool $showCart = false;

    public array $checkout = [];

    public function mount(): void
    {
        $this->guard('marketplace.view');
        $this->cart = Cart::all();
        $customer = auth()->user()->customer;
        $this->checkout = [
            'customer_id' => (string) ($customer?->id ?? ''),
            'delivery_address' => (string) ($customer?->delivery_address ?? ''),
            'expected_delivery_date' => today()->addDay()->format('Y-m-d'),
            'instructions' => '',
        ];
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['search', 'category'], true)) {
            $this->resetPage();
        }

        if (str_starts_with($name, 'cart.')) {
            $this->cart = array_filter($this->cart, fn ($qty) => (float) $qty > 0);
            Cart::put($this->cart);
            $this->dispatch('cart-updated');
        }

        if ($name === 'checkout.customer_id' && $this->isStaff()) {
            $this->checkout['delivery_address'] = (string) (Customer::find($this->checkout['customer_id'])?->delivery_address ?? '');
        }
    }

    /** Staff with order rights can place marketplace orders on behalf of a customer. */
    protected function isStaff(): bool
    {
        return auth()->user()->can('orders.manage');
    }

    #[On('open-cart')]
    public function openCart(): void
    {
        $this->showCart = true;
    }

    public function add(int $productId): void
    {
        $product = Product::where('status', 'active')->findOrFail($productId);
        $this->cart = Cart::all();
        Cart::add($product);
        $this->cart = Cart::all();
        $this->dispatch('cart-updated');
    }

    /** Step a cart line up or down; stepping below the minimum order quantity removes it. */
    public function step(int $productId, int $direction): void
    {
        $product = Product::where('status', 'active')->find($productId);

        if (! $product) {
            $this->remove($productId);

            return;
        }

        $current = (float) ($this->cart[$productId] ?? 0);
        $next = round($current + $direction, 2);

        if ($next < (float) $product->min_order_qty) {
            $this->remove($productId);

            return;
        }

        $this->cart[$productId] = $next;
        Cart::put($this->cart);
        $this->dispatch('cart-updated');
    }

    public function remove(int $productId): void
    {
        Cart::remove($productId);
        $this->cart = Cart::all();
        $this->dispatch('cart-updated');
    }

    public function clearCart(): void
    {
        Cart::clear();
        $this->cart = [];
        $this->dispatch('cart-updated');
    }

    public function placeOrder(OrderService $orders): void
    {
        $this->guard('marketplace.view');

        $this->validate([
            'cart' => ['required', 'array', 'min:1'],
            'cart.*' => ['numeric', 'min:0.01'],
            'checkout.customer_id' => [$this->isStaff() ? 'required' : 'nullable', 'exists:customers,id'],
            'checkout.delivery_address' => ['required', 'string', 'max:255'],
            'checkout.expected_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'checkout.instructions' => ['nullable', 'string', 'max:1000'],
        ], ['cart.required' => 'Your cart is empty.'], [
            'checkout.customer_id' => 'customer', 'checkout.delivery_address' => 'delivery location',
            'checkout.expected_delivery_date' => 'preferred delivery date', 'cart.*' => 'quantity',
        ]);

        // Customers can only ever order for their own account.
        $customer = $this->isStaff() ? Customer::find($this->checkout['customer_id']) : auth()->user()->customer;

        if (! $customer) {
            $this->toast('Your login is not linked to a customer account. Please contact Village City.', 'danger');

            return;
        }

        $items = collect($this->cart)->map(fn ($qty, $id) => ['product_id' => (int) $id, 'quantity' => (float) $qty])->values()->all();
        $order = null;

        $placed = $this->attempt(function () use ($orders, $customer, $items, &$order) {
            $order = $orders->place($customer, $items, [
                'delivery_address' => $this->checkout['delivery_address'],
                'expected_delivery_date' => $this->checkout['expected_delivery_date'],
                'instructions' => $this->checkout['instructions'] ?: null,
                'delivery_fee' => (float) Setting::get('default_delivery_fee', '0'),
            ], 'marketplace');
        });

        if ($placed) {
            Cart::clear();
            $this->cart = [];
            $this->dispatch('cart-updated');
            session()->flash('status', 'Order '.$order->reference.' placed. We will confirm it shortly.');
            $this->redirectRoute('orders.show', $order, navigate: true);
        }
    }

    public function render()
    {
        $stock = app(Metrics::class)->stockByProduct();

        $products = Product::with('category')->where('status', 'active')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->search}%")->orWhere('description', 'like', "%{$this->search}%")))
            ->when($this->category !== '', fn ($q) => $q->where('product_category_id', $this->category))
            ->orderBy('name')->paginate(12);

        // Best quality grade currently in stock per product (customers never see purchase costs).
        $grades = \App\Models\StockBatch::where('quantity', '>', 0)->whereIn('product_id', $products->pluck('id'))
            ->whereNotNull('grade')->get(['product_id', 'grade'])->groupBy('product_id')->map(fn ($g) => $g->min('grade'));

        $cartProducts = Product::whereIn('id', array_keys($this->cart))->get()->keyBy('id');
        $lines = collect($this->cart)->filter(fn ($q, $id) => $cartProducts->has($id))->map(fn ($qty, $id) => (object) [
            'product' => $cartProducts[$id],
            'quantity' => (float) $qty,
            'total' => round((float) $qty * (float) $cartProducts[$id]->selling_price, 2),
            'available' => $stock[$id] ?? 0.0,
        ]);

        $deliveryFee = (float) Setting::get('default_delivery_fee', '0');

        return $this->page('livewire.pages.marketplace', [
            'products' => $products,
            'stock' => $stock,
            'grades' => $grades,
            'categories' => ProductCategory::where('status', 'active')->orderBy('name')->pluck('name', 'id'),
            'lines' => $lines,
            'subtotal' => $lines->sum('total'),
            'deliveryFee' => $deliveryFee,
            'isStaff' => $this->isStaff(),
            'customers' => $this->isStaff() && $this->showCart ? Customer::where('status', 'active')->orderBy('name')->pluck('name', 'id') : collect(),
            'hasCustomer' => $this->isStaff() || auth()->user()->customer !== null,
        ], 'Marketplace');
    }
}
