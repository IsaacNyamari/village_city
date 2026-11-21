@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header image="site/produce-stall" title="Marketplace" description="Fresh produce, cereals and institutional supplies — sourced directly from farmers in Western Kenya and delivered within 24 hours.">
        <flux:button variant="primary" icon="shopping-cart" wire:click="$set('showCart', true)">
            Cart @if ($lines->count())<span class="ml-1 rounded-full bg-gold-400 px-1.5 text-xs font-semibold text-forest-950">{{ $lines->count() }}</span>@endif
        </flux:button>
    </x-ui.header>

    @unless ($hasCustomer)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="Your login is not linked to a customer account, so you can browse but not order." />
    @endunless

    <div class="flex flex-wrap items-center gap-3">
        <div class="w-full sm:w-72"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search commodities…" clearable /></div>
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="$set('category', '')" @class(['rounded-full border px-3 py-1.5 text-sm font-medium', 'border-forest-700 bg-forest-700 text-white' => $category === '', 'border-zinc-200 bg-white text-zinc-700 hover:border-forest-300' => $category !== ''])>All</button>
            @foreach ($categories as $id => $name)
                <button type="button" wire:click="$set('category', '{{ $id }}')" @class(['rounded-full border px-3 py-1.5 text-sm font-medium', 'border-forest-700 bg-forest-700 text-white' => $category === (string) $id, 'border-zinc-200 bg-white text-zinc-700 hover:border-forest-300' => $category !== (string) $id])>{{ $name }}</button>
            @endforeach
        </div>
    </div>

    @if ($products->isEmpty())
        <x-ui.panel><x-ui.empty icon="shopping-bag" title="No commodities match your search" text="Try a different search term or category." /></x-ui.panel>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($products as $product)
                @php $available = $stock[$product->id] ?? 0; @endphp
                <article data-product-card x-data="{ added: false }" class="flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs transition hover:shadow-md" wire:key="mp-{{ $product->id }}">
                    <div data-product-img class="flex h-40 items-center justify-center overflow-hidden bg-forest-50">
                        @if ($product->imageUrl())
                            <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="h-full w-full object-cover" loading="lazy" />
                        @else
                            <x-app-logo-icon class="size-12 text-forest-300" />
                        @endif
                    </div>
                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="truncate font-semibold text-zinc-900">{{ $product->name }}</h3>
                                <p class="truncate text-xs text-zinc-500">{{ $product->category?->name }}</p>
                            </div>
                            @if (isset($grades[$product->id]))<x-ui.status :value="$grades[$product->id]" />@endif
                        </div>
                        @if ($product->description)<p class="line-clamp-2 text-sm text-zinc-600">{{ $product->description }}</p>@endif
                        <div class="mt-auto space-y-2 pt-2">
                            <p class="text-lg font-semibold tabular-nums text-forest-800">{{ Options::money($product->selling_price) }} <span class="text-xs font-normal text-zinc-500">/ {{ $product->unit }}</span></p>
                            <div class="flex items-center justify-between text-xs">
                                @if ($available > 0)
                                    <span class="font-medium text-forest-700">{{ Options::qty($available) }} {{ $product->unit }} available</span>
                                @else
                                    <span class="font-medium text-gold-600">Sourced on order</span>
                                @endif
                                <span class="text-zinc-500">Min. {{ Options::qty($product->min_order_qty) }}</span>
                            </div>
                            <button type="button" wire:click="add({{ $product->id }})" @click="vcFly($el); added = true; setTimeout(() => added = false, 1400)"
                                :class="added ? 'bg-gold-400 text-forest-950' : '{{ isset($cart[$product->id]) ? 'bg-forest-50 text-forest-800 hover:bg-forest-100' : 'bg-forest-700 text-white hover:bg-forest-800' }}'"
                                class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition active:scale-95">
                                <span x-show="!added" class="inline-flex items-center gap-1.5"><flux:icon.shopping-cart variant="mini" class="size-4" />{{ isset($cart[$product->id]) ? 'In cart ('.Options::qty($cart[$product->id]).') · add more' : 'Add to cart' }}</span>
                                <span x-show="added" x-cloak class="inline-flex items-center gap-1.5"><svg class="vc-check size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>Added</span>
                            </button>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <div>{{ $products->links() }}</div>
    @endif

    <flux:modal wire:model.self="showCart" variant="flyout" class="w-full max-w-lg">
        <form wire:submit="placeOrder" class="space-y-5">
            <div>
                <flux:heading size="lg">Your cart</flux:heading>
                <flux:subheading>Review quantities, then tell us where and when to deliver.</flux:subheading>
            </div>

            @if ($lines->isEmpty())
                <x-ui.empty icon="shopping-cart" title="Your cart is empty" text="Add commodities from the marketplace to place an order." />
            @else
                <div class="flex justify-end"><button type="button" wire:click="clearCart" wire:confirm="Remove everything from your cart?" class="text-xs text-zinc-500 underline hover:text-red-600">Clear cart</button></div>
                <div class="space-y-3">
                    @foreach ($lines as $id => $line)
                        <div class="vc-rise rounded-lg border border-zinc-200 p-3" wire:key="cart-{{ $id }}">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-medium text-zinc-900">{{ $line->product->name }}</p>
                                    <p class="text-xs text-zinc-500">{{ Options::money($line->product->selling_price) }} / {{ $line->product->unit }}</p>
                                </div>
                                <flux:button type="button" size="sm" variant="ghost" icon="trash" wire:click="remove({{ $id }})" aria-label="Remove" />
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-3">
                                <div class="inline-flex items-center overflow-hidden rounded-lg border border-zinc-200">
                                    <button type="button" wire:click="step({{ $id }}, -1)" class="px-3 py-1.5 text-lg leading-none text-zinc-600 hover:bg-zinc-100 active:bg-zinc-200" aria-label="Decrease quantity">−</button>
                                    <input type="number" step="0.01" min="{{ (float) $line->product->min_order_qty }}" wire:model.live.debounce.500ms="cart.{{ $id }}" aria-label="Quantity" class="w-16 border-x border-zinc-200 py-1.5 text-center text-sm tabular-nums focus:outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
                                    <button type="button" wire:click="step({{ $id }}, 1)" class="px-3 py-1.5 text-lg leading-none text-zinc-600 hover:bg-zinc-100 active:bg-zinc-200" aria-label="Increase quantity">+</button>
                                </div>
                                <span class="font-semibold tabular-nums">{{ Options::money($line->total) }}</span>
                            </div>
                            @if ($line->quantity > $line->available)
                                <p class="mt-1.5 text-xs text-gold-600">{{ Options::qty($line->available) }} {{ $line->product->unit }} in stock now — the balance will be sourced from farmers after confirmation.</p>
                            @endif
                            <flux:error name="cart.{{ $id }}" />
                        </div>
                    @endforeach
                </div>

                <dl class="space-y-1 border-t border-zinc-200 pt-3 text-sm">
                    <div class="flex justify-between"><dt class="text-zinc-500">Subtotal</dt><dd class="tabular-nums">{{ Options::money($subtotal) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">Delivery charge</dt><dd class="tabular-nums">{{ Options::money($deliveryFee) }}</dd></div>
                    <div class="flex justify-between text-base font-semibold"><dt>Total</dt><dd class="tabular-nums">{{ Options::money($subtotal + $deliveryFee) }}</dd></div>
                </dl>

                @if ($isStaff)
                    <flux:select wire:model.live="checkout.customer_id" label="Order on behalf of customer *">
                        <flux:select.option value="">— Select customer —</flux:select.option>
                        @foreach ($customers as $id => $name)<flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>@endforeach
                    </flux:select>
                @endif
                <flux:input wire:model="checkout.delivery_address" label="Delivery location *" placeholder="Building, street, town" />
                <flux:input type="date" wire:model="checkout.expected_delivery_date" label="Preferred delivery date *" min="{{ today()->format('Y-m-d') }}" />
                <flux:textarea wire:model="checkout.instructions" label="Special instructions" rows="2" />
                <flux:error name="cart" />

                <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="placeOrder" :disabled="! $hasCustomer">Place order</flux:button>
            @endif
        </form>
    </flux:modal>
</div>
