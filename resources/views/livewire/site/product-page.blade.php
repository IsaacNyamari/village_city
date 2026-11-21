@php use App\Support\Options; $p = $product; $category = $p->category; @endphp
<div>
    <x-site.breadcrumbs :items="array_values(array_filter([['Home', route('home')], ['Produce', route('site.products')], $category ? [$category->name, route('site.category', $category->slug)] : null, [$p->name, null]]))" />

    <article class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8" data-product-card x-data="{ added: false }">
        <div class="grid gap-10 lg:grid-cols-2">
            <div data-product-img class="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-2xl bg-forest-50">
                @if ($p->imageUrl())
                    <img src="{{ $p->imageUrl() }}" alt="{{ $p->name }} — {{ $category?->name }} supplied by Village City" width="900" height="675" class="size-full object-cover" fetchpriority="high" decoding="async">
                @else
                    <x-app-logo-icon class="size-24 text-forest-300" />
                @endif
            </div>

            <div>
                @if ($category)<a href="{{ route('site.category', $category->slug) }}" wire:navigate class="text-sm font-semibold uppercase tracking-widest text-forest-600 hover:underline">{{ $category->name }}</a>@endif
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-zinc-900 sm:text-4xl">{{ $p->name }}</h1>

                <p class="mt-4 text-3xl font-semibold tabular-nums text-forest-800">{{ Options::money($p->selling_price) }} <span class="text-base font-normal text-zinc-500">per {{ $p->unit }}</span></p>
                <p class="mt-1 text-sm {{ $inStock ? 'text-forest-700' : 'text-gold-600' }}">{{ $inStock ? 'In stock — ready for dispatch' : 'Sourced on order from our farmers' }}</p>

                @if ($p->description)<p class="mt-5 leading-relaxed text-zinc-700">{{ $p->description }}</p>@endif

                <dl class="mt-6 grid grid-cols-2 gap-4 rounded-xl border border-zinc-200 p-4 text-sm">
                    <div><dt class="text-zinc-500">Minimum order</dt><dd class="font-medium text-zinc-900">{{ Options::qty($p->min_order_qty) }} {{ $p->unit }}</dd></div>
                    <div><dt class="text-zinc-500">Product code</dt><dd class="font-medium text-zinc-900">{{ $p->code }}</dd></div>
                    <div><dt class="text-zinc-500">Type</dt><dd class="font-medium text-zinc-900">{{ $p->is_perishable ? 'Fresh / perishable' : 'Dry / non-perishable' }}</dd></div>
                    @if ($p->storage_requirements)<div><dt class="text-zinc-500">Storage</dt><dd class="font-medium text-zinc-900">{{ $p->storage_requirements }}</dd></div>@endif
                </dl>

                <div class="mt-6 flex flex-wrap gap-3">
                    <button type="button" wire:click="add" @click="vcFly($el); added = true; setTimeout(() => added = false, 1400)"
                        :class="added ? 'bg-gold-400 text-forest-950' : 'bg-forest-700 text-white hover:bg-forest-800'"
                        class="inline-flex items-center justify-center gap-2 rounded-lg px-6 py-3 text-sm font-medium transition active:scale-95">
                        <span x-show="!added" class="inline-flex items-center gap-2"><flux:icon.shopping-cart variant="mini" class="size-4" />{{ $inCart ? 'In cart ('.Options::qty($inCart).') · add more' : 'Add to cart' }}</span>
                        <span x-show="added" x-cloak class="inline-flex items-center gap-2"><svg class="vc-check size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>Added to cart</span>
                    </button>
                    @if ($cartCount)<flux:button class="!py-3" wire:click="checkout">{{ auth()->check() ? 'Checkout' : 'Log in to checkout' }} ({{ $cartCount }})</flux:button>@endif
                </div>
                <p class="mt-3 text-xs text-zinc-500">Prices are indicative and confirmed at checkout. Wholesale and institutional buyers can <a href="{{ route('site.contact') }}" class="underline" wire:navigate>contact us</a> for volume pricing.</p>
            </div>
        </div>

        <section class="mt-14 grid gap-6 md:grid-cols-3" aria-label="Why buy from Village City">
            @foreach ([['Direct from farmers', 'Sourced from smallholder farmers and cooperatives across Western Kenya.', 'users'], ['Quality graded', 'Inspected and graded at the collection centre before it reaches you.', 'check-badge'], ['Delivered fast', 'Farm to city within 24 hours, with delivery tracking.', 'truck']] as [$t, $d, $icon])
                <div class="rounded-2xl border border-zinc-200 p-5"><flux:icon :name="$icon" class="size-6 text-forest-700" /><h2 class="mt-3 font-semibold text-zinc-900">{{ $t }}</h2><p class="mt-1 text-sm text-zinc-600">{{ $d }}</p></div>
            @endforeach
        </section>

        @if ($related->isNotEmpty())
            <section class="mt-14" aria-labelledby="related-title">
                <h2 id="related-title" class="text-2xl font-semibold tracking-tight text-zinc-900">More from {{ $category?->name ?? 'our range' }}</h2>
                <div class="mt-5 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    @foreach ($related as $r)
                        <a href="{{ route('site.product', $r->slug) }}" wire:navigate class="group overflow-hidden rounded-xl border border-zinc-200 bg-white transition hover:shadow-md">
                            <div class="flex h-32 items-center justify-center overflow-hidden bg-forest-50">@if ($r->imageUrl())<img src="{{ $r->imageUrl() }}" alt="{{ $r->name }}" class="size-full object-cover transition duration-500 group-hover:scale-105" loading="lazy" decoding="async">@else<x-app-logo-icon class="size-8 text-forest-300" />@endif</div>
                            <div class="p-3"><h3 class="truncate text-sm font-medium text-zinc-900">{{ $r->name }}</h3><p class="text-xs text-zinc-500">{{ Options::money($r->selling_price) }} / {{ $r->unit }}</p></div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </article>
</div>
