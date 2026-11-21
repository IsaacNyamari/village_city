@php use App\Support\Options; @endphp
<div>
    <x-site.page-hero image="site/produce-stall" :eyebrow="$category ? 'Produce range' : 'Catalogue'" :title="$heroTitle" :text="$heroText" />
    <x-site.breadcrumbs :items="$category ? [['Home', route('home')], ['Produce', route('site.products')], [$category->name, null]] : [['Home', route('home')], ['Produce', null]]" />

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-72"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search produce…" clearable aria-label="Search produce" /></div>
            <nav class="flex flex-wrap gap-2" aria-label="Product categories">
                <a href="{{ route('site.products') }}" wire:navigate @class(['rounded-full border px-3 py-1.5 text-sm font-medium', 'border-forest-700 bg-forest-700 text-white' => ! $category, 'border-zinc-200 text-zinc-700 hover:border-forest-300' => $category])>All</a>
                @foreach ($categories as $c)
                    <a href="{{ route('site.category', $c->slug) }}" wire:navigate @class(['rounded-full border px-3 py-1.5 text-sm font-medium', 'border-forest-700 bg-forest-700 text-white' => $category?->id === $c->id, 'border-zinc-200 text-zinc-700 hover:border-forest-300' => $category?->id !== $c->id])>{{ $c->name }}</a>
                @endforeach
            </nav>
        </div>

        @if ($products->isEmpty())
            <div class="mt-10 rounded-xl border border-dashed border-zinc-300 p-12 text-center text-sm text-zinc-500">No produce matches your search.</div>
        @else
            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($products as $product)
                    <article data-product-card x-data="{ added: false }" class="flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white transition hover:shadow-md" wire:key="p-{{ $product->id }}">
                        <a href="{{ route('site.product', $product->slug) }}" wire:navigate data-product-img class="flex h-40 items-center justify-center overflow-hidden bg-forest-50" aria-label="{{ $product->name }} details">
                            @if ($product->imageUrl())<img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" width="900" height="600" class="size-full object-cover" loading="lazy" decoding="async">@else<x-app-logo-icon class="size-12 text-forest-300" />@endif
                        </a>
                        <div class="flex flex-1 flex-col gap-1 p-4">
                            <p class="text-xs text-zinc-500">{{ $product->category?->name }}</p>
                            <h2 class="font-semibold text-zinc-900"><a href="{{ route('site.product', $product->slug) }}" wire:navigate class="hover:text-forest-700 hover:underline">{{ $product->name }}</a></h2>
                            @if ($product->description)<p class="line-clamp-2 text-sm text-zinc-600">{{ $product->description }}</p>@endif
                            <p class="mt-auto pt-3 text-lg font-semibold tabular-nums text-forest-800">{{ Options::money($product->selling_price) }} <span class="text-xs font-normal text-zinc-500">/ {{ $product->unit }}</span></p>
                            <p class="text-xs text-zinc-500">Minimum order {{ Options::qty($product->min_order_qty) }} {{ $product->unit }}</p>
                            <button type="button" wire:click="add({{ $product->id }})" @click="vcFly($el); added = true; setTimeout(() => added = false, 1400)"
                                :class="added ? 'bg-gold-400 text-forest-950' : 'bg-forest-700 text-white hover:bg-forest-800'"
                                class="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition active:scale-95">
                                <span x-show="!added" class="inline-flex items-center gap-1.5"><flux:icon.shopping-cart variant="mini" class="size-4" />{{ isset($cart[$product->id]) ? 'In cart ('.Options::qty($cart[$product->id]).') · add more' : 'Add to cart' }}</span>
                                <span x-show="added" x-cloak class="inline-flex items-center gap-1.5"><svg class="vc-check size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>Added</span>
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="mt-8">{{ $products->links() }}</div>
        @endif

        @if (count($cart))
            <div class="vc-rise fixed inset-x-0 bottom-4 z-40 mx-auto flex w-[calc(100%-2rem)] max-w-xl items-center justify-between gap-3 rounded-2xl bg-forest-900 px-4 py-3 text-white shadow-2xl">
                <div><p class="text-sm font-semibold">{{ count($cart) }} item(s) in your cart</p><p class="text-xs text-forest-200">Estimated {{ Options::money($cartTotal) }} · final price confirmed at checkout</p></div>
                <flux:button variant="primary" class="!bg-gold-400 !text-forest-950 hover:!bg-gold-300" wire:click="checkout">{{ auth()->check() ? 'Checkout' : 'Log in to checkout' }}</flux:button>
            </div>
        @endif

        <div class="mt-12 flex flex-col items-center gap-3 rounded-2xl bg-forest-50 p-8 text-center">
            <h2 class="text-lg font-semibold text-forest-900">Ready to order?</h2>
            <p class="max-w-lg text-sm text-zinc-600">Prices are indicative and may change with the season. Log in or create a buyer account to see live availability and place an order.</p>
            @auth
                <flux:button variant="primary" :href="route('marketplace')" wire:navigate>Go to the marketplace</flux:button>
            @else
                <div class="flex gap-2"><flux:button variant="primary" :href="route('register')" wire:navigate>Create buyer account</flux:button><flux:button :href="route('login')" wire:navigate>Log in</flux:button></div>
            @endauth
        </div>
    </div>
</div>
