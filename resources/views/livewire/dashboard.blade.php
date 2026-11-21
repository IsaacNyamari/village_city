@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header image="site/market-crates" title="Executive dashboard" description="Live figures from your orders, procurement, stock and deliveries. Gross profit is goods revenue less cost of stock sold — it is not net profit.">
        <flux:select wire:model.live="days" class="w-40" aria-label="Period">
            <flux:select.option value="7">Last 7 days</flux:select.option>
            <flux:select.option value="30">Last 30 days</flux:select.option>
            <flux:select.option value="90">Last 90 days</flux:select.option>
        </flux:select>
    </x-ui.header>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-5">
        <x-ui.stat label="Total revenue" :value="Options::money($k['revenue'])" icon="banknotes" hint="Confirmed & allocated orders" />
        <x-ui.stat label="Total procurement" :value="Options::money($k['procurement'])" icon="shopping-cart" tone="blue" hint="Accepted produce" />
        <x-ui.stat label="Gross profit" :value="Options::money($k['gross_profit'])" icon="chart-bar" tone="gold" hint="Before operating expenses" />
        <x-ui.stat label="Outstanding payments" :value="Options::money($k['outstanding'])" icon="exclamation-circle" tone="red" hint="Customer invoices" :href="route('invoices.index')" />
        <x-ui.stat label="Active farmers" :value="number_format($k['active_farmers'])" icon="users" :href="route('farmers.index')" />
        <x-ui.stat label="Active customers" :value="number_format($k['active_customers'])" icon="identification" tone="blue" />
        <x-ui.stat label="Total orders" :value="number_format($k['total_orders'])" icon="shopping-bag" />
        <x-ui.stat label="Pending orders" :value="number_format($k['pending_orders'])" icon="clock" tone="gold" :href="route('orders.index')" />
        <x-ui.stat label="Products in stock" :value="number_format($k['products_in_stock'])" icon="archive-box" :href="route('inventory.index')" />
        <x-ui.stat label="Active deliveries" :value="number_format($k['active_deliveries'])" icon="truck" tone="blue" :href="route('deliveries.index', ['status' => 'active'])" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.panel title="Sales performance" :description="'Order value by confirmation date · last '.$days.' days'" class="lg:col-span-2" padded>
            <x-ui.chart type="line" :labels="$sales['labels']" :datasets="[['label' => 'Sales (KES)', 'data' => $sales['values']]]" />
        </x-ui.panel>
        <x-ui.panel title="Revenue by category" padded>
            <x-ui.chart type="doughnut" :labels="$byCategory->pluck('label')" :datasets="[['label' => 'Revenue', 'data' => $byCategory->pluck('value')->map(fn ($v) => (float) $v)->all()]]" />
        </x-ui.panel>
        <x-ui.panel title="Procurement expenditure" description="Payable value of accepted produce" class="lg:col-span-2" padded>
            <x-ui.chart type="bar" :labels="$spend['labels']" :datasets="[['label' => 'Procurement (KES)', 'data' => $spend['values']]]" />
        </x-ui.panel>
        <x-ui.panel title="Orders by status" padded>
            <x-ui.chart type="doughnut" :labels="$orderStatuses->keys()->map(fn ($s) => ucwords(str_replace('_', ' ', $s)))->all()" :datasets="[['label' => 'Orders', 'data' => $orderStatuses->values()->all()]]" />
        </x-ui.panel>
        <x-ui.panel title="Farmer supply performance" description="Accepted quantity by farmer" class="lg:col-span-2" padded>
            <x-ui.chart type="bar" :horizontal="true" :labels="$farmerSupply->pluck('label')" :datasets="[['label' => 'Accepted quantity', 'data' => $farmerSupply->pluck('value')->map(fn ($v) => (float) $v)->all()]]" />
        </x-ui.panel>
        <x-ui.panel title="Delivery performance" padded>
            <x-ui.chart type="doughnut" :labels="$deliveryStatuses->keys()->map(fn ($s) => ucwords(str_replace('_', ' ', $s)))->all()" :datasets="[['label' => 'Deliveries', 'data' => $deliveryStatuses->values()->all()]]" />
        </x-ui.panel>
    </div>

    <div class="grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
        <x-ui.panel title="Recent orders">
            <x-slot:actions><flux:button size="xs" variant="ghost" :href="route('orders.index')" wire:navigate>View all</flux:button></x-slot:actions>
            @forelse ($recentOrders as $o)
                <a href="{{ route('orders.show', $o) }}" wire:navigate class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-2.5 last:border-0 hover:bg-zinc-50">
                    <div class="min-w-0"><p class="truncate text-sm font-medium">{{ $o->reference }} · {{ $o->customer->name }}</p><p class="text-xs text-zinc-500">{{ $o->created_at->diffForHumans() }}</p></div>
                    <div class="text-right"><p class="text-sm tabular-nums">{{ Options::money($o->total) }}</p><x-ui.status :value="$o->status" /></div>
                </a>
            @empty <x-ui.empty icon="shopping-cart" title="No orders yet" /> @endforelse
        </x-ui.panel>

        <x-ui.panel title="Recent procurement">
            <x-slot:actions><flux:button size="xs" variant="ghost" :href="route('procurement.index')" wire:navigate>View all</flux:button></x-slot:actions>
            @forelse ($recentProcurements as $p)
                <a href="{{ route('procurement.show', $p) }}" wire:navigate class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-2.5 last:border-0 hover:bg-zinc-50">
                    <div class="min-w-0"><p class="truncate text-sm font-medium">{{ $p->reference }} · {{ $p->sourceName() }}</p><p class="text-xs text-zinc-500">{{ $p->procurement_date->format('d M Y') }}</p></div>
                    <x-ui.status :value="$p->status" />
                </a>
            @empty <x-ui.empty icon="clipboard-document-list" title="No procurement yet" /> @endforelse
        </x-ui.panel>

        <x-ui.panel title="Latest farmer registrations">
            @forelse ($newFarmers as $f)
                <a href="{{ route('farmers.show', $f) }}" wire:navigate class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-2.5 last:border-0 hover:bg-zinc-50">
                    <div><p class="text-sm font-medium">{{ $f->name }}</p><p class="text-xs text-zinc-500">{{ $f->number }} · {{ $f->county?->name ?? 'No county' }}</p></div>
                    <span class="text-xs text-zinc-500">{{ $f->registered_at?->format('d M Y') }}</span>
                </a>
            @empty <x-ui.empty icon="users" title="No farmers registered" /> @endforelse
        </x-ui.panel>

        <x-ui.panel title="Low-stock alerts">
            @forelse ($lowStock as $p)
                <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-2.5 last:border-0">
                    <p class="text-sm font-medium">{{ $p->name }}</p>
                    <span class="text-sm tabular-nums {{ $p->on_hand <= 0 ? 'text-red-600' : 'text-gold-600' }}">{{ Options::qty($p->on_hand) }} / min {{ Options::qty($p->min_stock_level) }} {{ $p->unit }}</span>
                </div>
            @empty <x-ui.empty icon="check-circle" title="Stock levels are healthy" /> @endforelse
        </x-ui.panel>

        <x-ui.panel title="Pending supplier payments">
            <x-slot:actions><flux:button size="xs" variant="ghost" :href="route('settlements.index')" wire:navigate>View all</flux:button></x-slot:actions>
            @forelse ($pendingSupplier as $p)
                <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-2.5 last:border-0">
                    <div class="min-w-0"><p class="truncate text-sm font-medium">{{ $p->sourceName() }}</p><p class="text-xs text-zinc-500">{{ $p->reference }}</p></div>
                    <span class="text-sm tabular-nums">{{ Options::money($p->balance()) }}</span>
                </div>
            @empty <x-ui.empty icon="check-circle" title="No supplier payments due" /> @endforelse
        </x-ui.panel>

        <x-ui.panel title="Outstanding customer invoices">
            <x-slot:actions><flux:button size="xs" variant="ghost" :href="route('invoices.index')" wire:navigate>View all</flux:button></x-slot:actions>
            @forelse ($openInvoices as $i)
                <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-2.5 last:border-0">
                    <div class="min-w-0"><p class="truncate text-sm font-medium">{{ $i->number }} · {{ $i->customer->name }}</p><p class="text-xs {{ $i->isOverdue() ? 'text-red-600' : 'text-zinc-500' }}">Due {{ $i->due_date?->format('d M Y') }}</p></div>
                    <span class="text-sm tabular-nums">{{ Options::money($i->balance()) }}</span>
                </div>
            @empty <x-ui.empty icon="check-circle" title="No outstanding invoices" /> @endforelse
        </x-ui.panel>

        <x-ui.panel title="Active deliveries">
            @forelse ($activeDeliveries as $d)
                <a href="{{ route('deliveries.show', $d) }}" wire:navigate class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-2.5 last:border-0 hover:bg-zinc-50">
                    <div class="min-w-0"><p class="truncate text-sm font-medium">{{ $d->reference }} · {{ $d->order->customer->name }}</p><p class="text-xs text-zinc-500">{{ $d->expected_at ? 'Expected '.$d->expected_at->format('d M H:i') : 'No ETA' }}</p></div>
                    <x-ui.status :value="$d->status" />
                </a>
            @empty <x-ui.empty icon="truck" title="No active deliveries" /> @endforelse
        </x-ui.panel>

        <x-ui.panel title="Top-selling commodities" :description="'Last '.$days.' days'">
            @forelse ($topProducts as $p)
                <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-2.5 last:border-0">
                    <div><p class="text-sm font-medium">{{ $p->label }}</p><p class="text-xs text-zinc-500">{{ Options::qty($p->qty) }} {{ $p->unit }}</p></div>
                    <span class="text-sm tabular-nums">{{ Options::money($p->value) }}</span>
                </div>
            @empty <x-ui.empty icon="chart-bar" title="No sales in this period" /> @endforelse
        </x-ui.panel>

        <x-ui.panel title="Activity by county">
            <x-slot:actions><flux:button size="xs" variant="ghost" :href="route('counties.index')" wire:navigate>All counties</flux:button></x-slot:actions>
            @forelse ($counties as $c)
                <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-2.5 last:border-0">
                    <p class="text-sm font-medium">{{ $c->name }}</p>
                    <span class="text-xs text-zinc-500">{{ $c->farmers_count }} farmers · {{ $c->suppliers_count }} suppliers · {{ $c->centres_count }} centres</span>
                </div>
            @empty <x-ui.empty icon="globe-europe-africa" title="No counties" /> @endforelse
        </x-ui.panel>

        <x-ui.panel title="Operational notifications">
            @forelse ($notifications as $n)
                <div class="flex items-start gap-3 border-b border-zinc-100 px-5 py-2.5 last:border-0">
                    <flux:icon :name="$n->data['icon'] ?? 'bell'" variant="mini" class="mt-0.5 size-4 shrink-0 text-forest-600" />
                    <div class="min-w-0"><p class="truncate text-sm font-medium">{{ $n->data['title'] }}</p><p class="text-xs text-zinc-500">{{ $n->created_at->diffForHumans() }}</p></div>
                </div>
            @empty <x-ui.empty icon="bell" title="No notifications" /> @endforelse
        </x-ui.panel>
    </div>
</div>
