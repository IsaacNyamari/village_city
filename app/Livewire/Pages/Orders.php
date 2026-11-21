<?php

namespace App\Livewire\Pages;

use App\Enums\OrderStatus;
use App\Livewire\Concerns\Page;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Metrics;
use App\Services\OrderService;
use App\Support\Options;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Orders extends Component
{
    use Page, WithPagination;

    public string $search = '';

    #[Url]
    public string $status = '';

    public string $payment = '';

    public bool $showForm = false;

    public array $form = [];

    public array $items = [];

    public function mount(): void
    {
        $this->guard('orders.access');
    }

    public function updated(string $name, mixed $value): void
    {
        if (in_array($name, ['search', 'status', 'payment'], true)) {
            $this->resetPage();
        }

        if (preg_match('/^items\.(\d+)\.product_id$/', $name, $m) && $value) {
            $this->items[(int) $m[1]]['unit_price'] = (string) (Product::find($value)?->selling_price ?? '');
        }

        if ($name === 'form.customer_id' && $value) {
            $this->form['delivery_address'] = (string) (Customer::find($value)?->delivery_address ?? '');
        }
    }

    public function create(): void
    {
        $this->guard('orders.manage');
        $this->resetValidation();
        $this->form = [
            'customer_id' => '', 'delivery_address' => '', 'expected_delivery_date' => today()->addDay()->format('Y-m-d'),
            'discount' => '0', 'delivery_fee' => Setting::get('default_delivery_fee', '0'), 'instructions' => '',
        ];
        $this->items = [['product_id' => '', 'quantity' => '', 'unit_price' => '', 'discount' => '0']];
        $this->showForm = true;
    }

    public function addItem(): void
    {
        $this->items[] = ['product_id' => '', 'quantity' => '', 'unit_price' => '', 'discount' => '0'];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(OrderService $orders): void
    {
        $this->guard('orders.manage');

        $this->validate([
            'form.customer_id' => ['required', 'exists:customers,id'],
            'form.delivery_address' => ['required', 'string', 'max:255'],
            'form.expected_delivery_date' => ['required', 'date'],
            'form.discount' => ['nullable', 'numeric', 'min:0'],
            'form.delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'form.instructions' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ], ['items.*.product_id.distinct' => 'Each product can only appear once.'], [
            'form.customer_id' => 'customer', 'form.delivery_address' => 'delivery address', 'form.expected_delivery_date' => 'expected delivery date',
            'items.*.product_id' => 'product', 'items.*.quantity' => 'quantity', 'items.*.unit_price' => 'unit price', 'items.*.discount' => 'discount',
        ]);

        $order = null;

        if ($this->attempt(function () use ($orders, &$order) {
            $order = $orders->place(Customer::findOrFail($this->form['customer_id']), $this->items, $this->form, 'internal');
        }, 'Order created and awaiting confirmation.')) {
            $this->showForm = false;
            $this->redirectRoute('orders.show', $order, navigate: true);
        }
    }

    public function render()
    {
        $user = auth()->user();
        $staff = $user->can('orders.view');

        $rows = Order::query()->with(['customer', 'items'])
            // Customers only ever see their own orders.
            ->when(! $staff, fn ($q) => $q->where('customer_id', $user->customer?->id ?? 0))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%"))))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->payment !== '', fn ($q) => $q->where('payment_status', $this->payment))
            ->latest('id')->paginate(15);

        return $this->page('livewire.pages.orders', [
            'rows' => $rows,
            'staff' => $staff,
            'statuses' => OrderStatus::cases(),
            'customers' => $this->showForm ? Customer::where('status', 'active')->orderBy('name')->get(['id', 'name', 'number']) : collect(),
            'products' => $this->showForm ? Product::where('status', 'active')->orderBy('name')->get(['id', 'name', 'unit', 'selling_price']) : collect(),
            'stock' => $this->showForm ? app(Metrics::class)->stockByProduct() : collect(),
            'formTotal' => collect($this->items)->sum(fn ($i) => max(0, (float) ($i['quantity'] ?: 0) * (float) ($i['unit_price'] ?: 0) - (float) ($i['discount'] ?: 0)))
                - (float) ($this->form['discount'] ?? 0) + (float) ($this->form['delivery_fee'] ?? 0),
        ], $staff ? 'Orders' : 'My Orders');
    }
}
