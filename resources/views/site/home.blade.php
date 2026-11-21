@php
    use App\Support\Options;
    use App\Support\SiteContent as C;
@endphp
<x-layouts.site :seo="$seo">
    {{-- Hero --}}
    <section class="relative isolate overflow-hidden bg-forest-950 text-white" aria-labelledby="hero-title">
        <img src="{{ $heroImage }}" alt="{{ C::get('home.hero_image_alt') }}" class="absolute inset-0 -z-10 size-full object-cover opacity-50" fetchpriority="high" decoding="async">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-forest-950 via-forest-950/75 to-forest-900/10"></div>
        <div class="mx-auto max-w-7xl px-4 pb-28 pt-20 sm:px-6 sm:pt-28 lg:px-8">
            <p class="vc-rise inline-flex rounded-full bg-forest-800/80 px-3 py-1 text-xs font-medium uppercase tracking-widest text-gold-300">{{ C::get('home.hero_eyebrow') }}</p>
            <h1 id="hero-title" class="vc-rise mt-5 max-w-3xl text-4xl font-semibold leading-tight tracking-tight sm:text-6xl">{{ C::get('home.hero_title') }} <span class="text-gold-300">{{ C::get('home.hero_title_accent') }}</span></h1>
            <p class="vc-rise mt-5 max-w-xl text-lg leading-relaxed text-forest-100">{{ C::get('home.hero_text') }}</p>
            <div class="vc-rise mt-8 flex flex-wrap gap-3">
                <flux:button variant="primary" class="!bg-gold-400 !text-forest-950 hover:!bg-gold-300" :href="route('site.products')" wire:navigate>{{ C::get('home.hero_cta_primary') }}</flux:button>
                <flux:button :href="route('site.sell')" wire:navigate>{{ C::get('home.hero_cta_secondary') }}</flux:button>
            </div>
        </div>
    </section>

    {{-- Live stats --}}
    <section class="relative z-10 mx-auto -mt-16 max-w-6xl px-4 sm:px-6 lg:px-8" aria-label="Village City in numbers">
        <div class="grid grid-cols-2 gap-3 rounded-2xl border border-zinc-200 bg-white p-4 shadow-xl sm:grid-cols-4 sm:gap-0 sm:divide-x sm:divide-zinc-200 sm:p-6">
            @foreach ([['Farmers', $stats['farmers'], 'users'], ['Counties sourced', $stats['counties'], 'map-pin'], ['Commodities', $stats['products'], 'cube'], ['Collection centres', $stats['centres'], 'building-office-2']] as [$label, $value, $icon])
                <div class="flex items-center gap-3 px-3 sm:justify-center">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-forest-50 text-forest-700"><flux:icon :name="$icon" class="size-6" /></span>
                    <div><p class="text-2xl font-semibold tabular-nums text-zinc-900">{{ number_format($value) }}</p><p class="text-xs text-zinc-500">{{ $label }}</p></div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Who we supply --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8" aria-labelledby="supply-title">
        <p class="text-sm font-semibold uppercase tracking-widest text-forest-600">Who we supply</p>
        <h2 id="supply-title" class="mt-2 text-3xl font-semibold tracking-tight text-zinc-900">{{ C::get('home.supply_heading') }}</h2>
        <div class="mt-8 grid gap-5 md:grid-cols-3">
            @foreach ([
                ['images/site/market-stall.jpg', 'Wholesalers & traders', 'Bulk volumes of fresh produce and cereals, priced for resale.', 'Colourful fruit and vegetable stalls at a busy wholesale market'],
                ['images/site/produce-stall.jpg', 'Supermarkets, restaurants & hotels', 'Consistent grading and scheduled deliveries for busy kitchens and shelves.', 'A well-stocked fresh produce display with cabbages, greens and herbs'],
                ['images/site/delivery.jpg', 'Schools, hospitals & institutions', 'Dependable institutional supply with invoicing and credit terms.', 'A delivery van loaded with boxes of produce'],
            ] as [$img, $title, $text, $alt])
                <article class="group overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs transition hover:shadow-lg">
                    <div class="h-48 overflow-hidden"><img src="{{ asset($img) }}" alt="{{ $alt }}" width="900" height="600" class="size-full object-cover transition duration-500 group-hover:scale-105" loading="lazy" decoding="async"></div>
                    <div class="p-5"><h3 class="font-semibold text-zinc-900">{{ $title }}</h3><p class="mt-1 text-sm leading-relaxed text-zinc-600">{{ $text }}</p></div>
                </article>
            @endforeach
        </div>
    </section>

    {{-- How it works --}}
    <section class="bg-forest-50 py-20" aria-labelledby="how-title">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div class="relative">
                <img src="{{ asset('images/site/hero-harvest.jpg') }}" alt="A farmer carrying a basket of freshly harvested vegetables" width="900" height="1125" class="aspect-[4/5] w-full rounded-3xl object-cover shadow-xl" loading="lazy" decoding="async">
                <div class="absolute -bottom-5 right-4 rounded-2xl bg-white p-4 shadow-lg sm:right-8">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Farm gate to doorstep</p>
                    <p class="text-2xl font-semibold text-forest-800">&lt; 24 hours</p>
                </div>
            </div>
            <div>
                <p class="text-sm font-semibold uppercase tracking-widest text-forest-600">How it works</p>
                <h2 id="how-title" class="mt-2 text-3xl font-semibold tracking-tight text-zinc-900">{{ C::get('home.how_heading') }}</h2>
                <ol class="mt-8 space-y-5">
                    @foreach (C::pairs('home.how_steps') as $i => [$title, $text])
                        <li class="flex gap-4">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-forest-700 text-sm font-semibold text-white">{{ $i + 1 }}</span>
                            <div><h3 class="font-semibold text-zinc-900">{{ $title }}</h3><p class="mt-1 text-sm leading-relaxed text-zinc-600">{{ $text }}</p></div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    {{-- Product ranges --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8" aria-labelledby="ranges-title">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-widest text-forest-600">Our ranges</p>
                <h2 id="ranges-title" class="mt-2 text-3xl font-semibold tracking-tight text-zinc-900">{{ C::get('home.ranges_heading') }}</h2>
            </div>
            <flux:button :href="route('site.products')" wire:navigate icon-trailing="arrow-right">View full catalogue</flux:button>
        </div>

        @php
            $rangeImages = ['Village City Fresh' => 'images/site/produce-stall.jpg', 'Village City Cereals and Dry Foods' => 'images/products/maize.jpg', 'Village City Institutional Supply' => 'images/site/delivery.jpg'];
        @endphp
        <div class="mt-8 grid gap-6 md:grid-cols-3">
            @forelse ($categories as $category)
                <a href="{{ route('site.category', $category->slug) }}" wire:navigate class="group relative isolate flex min-h-80 flex-col justify-end overflow-hidden rounded-2xl text-white shadow-xs">
                    <img src="{{ asset($rangeImages[$category->name] ?? 'images/site/market-crates.jpg') }}" alt="{{ $category->name }}" class="absolute inset-0 -z-10 size-full object-cover transition duration-500 group-hover:scale-105" loading="lazy" decoding="async">
                    <div class="absolute inset-0 -z-10 bg-gradient-to-t from-forest-950/90 via-forest-950/40 to-transparent"></div>
                    <div class="p-6">
                        <h3 class="text-xl font-semibold">{{ $category->name }}</h3>
                        <p class="mt-1 text-sm text-forest-100">{{ $category->description }}</p>
                        <p class="mt-3 text-xs text-forest-200">{{ $category->products->pluck('name')->take(5)->implode(' · ') }}{{ $category->products_count > 5 ? ' …' : '' }}</p>
                    </div>
                </a>
            @empty
                <p class="text-sm text-zinc-500">Our catalogue is being prepared.</p>
            @endforelse
        </div>

        @if ($featured->isNotEmpty())
            <div class="mt-10 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($featured as $product)
                    <a href="{{ route('site.product', $product->slug) }}" wire:navigate class="group overflow-hidden rounded-xl border border-zinc-200 bg-white transition hover:shadow-md">
                        <div class="flex h-28 items-center justify-center overflow-hidden bg-forest-50">
                            @if ($product->imageUrl())<img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="size-full object-cover transition duration-500 group-hover:scale-110" loading="lazy" decoding="async">@else<x-app-logo-icon class="size-8 text-forest-300" />@endif
                        </div>
                        <div class="p-3">
                            <h3 class="truncate text-sm font-medium text-zinc-900">{{ $product->name }}</h3>
                            <p class="text-xs text-zinc-500">{{ Options::money($product->selling_price) }} / {{ $product->unit }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Sourcing counties --}}
    <section class="relative isolate overflow-hidden bg-forest-950 py-20 text-white" aria-labelledby="counties-title">
        <img src="{{ asset('images/site/hero-farmer.jpg') }}" alt="" class="absolute inset-0 -z-10 size-full object-cover opacity-25" loading="lazy" decoding="async">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <p class="text-sm font-semibold uppercase tracking-widest text-gold-300">Where we source</p>
            <h2 id="counties-title" class="mt-2 text-3xl font-semibold tracking-tight">{{ C::get('home.counties_heading') }}</h2>
            <p class="mt-3 max-w-2xl text-forest-100">{{ C::get('home.counties_text') }}</p>
            <ul class="mt-8 flex flex-wrap gap-2">
                @foreach ($counties as $county)<li class="rounded-full border border-forest-600 bg-forest-900/70 px-4 py-1.5 text-sm backdrop-blur">{{ $county->name }}</li>@endforeach
            </ul>
        </div>
    </section>

    {{-- For farmers --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8" aria-labelledby="farmers-title">
        <div class="grid items-center gap-10 overflow-hidden rounded-3xl bg-forest-50 lg:grid-cols-2">
            <div class="p-8 sm:p-12">
                <p class="text-sm font-semibold uppercase tracking-widest text-forest-600">For farmers & cooperatives</p>
                <h2 id="farmers-title" class="mt-2 text-3xl font-semibold tracking-tight text-zinc-900">{{ C::get('home.farmers_heading') }}</h2>
                <ul class="mt-5 space-y-2 text-sm text-zinc-700">
                    @foreach (C::lines('home.farmers_points') as $point)
                        <li class="flex gap-2"><flux:icon.check-circle class="size-5 shrink-0 text-forest-600" />{{ $point }}</li>
                    @endforeach
                </ul>
                <flux:button class="mt-6" variant="primary" :href="route('site.sell')" wire:navigate>Register as a farmer</flux:button>
            </div>
            <img src="{{ asset('images/site/hero-harvest.jpg') }}" alt="A smiling farmer in a field with a harvest basket" class="h-72 w-full object-cover lg:h-full" loading="lazy" decoding="async">
        </div>
    </section>

    {{-- FAQ --}}
    @if ($faqs->isNotEmpty())
        <section class="mx-auto max-w-3xl px-4 pb-4 sm:px-6 lg:px-8" aria-labelledby="faq-title">
            <h2 id="faq-title" class="text-3xl font-semibold tracking-tight text-zinc-900">Common questions</h2>
            <div class="mt-6 divide-y divide-zinc-200 rounded-2xl border border-zinc-200">
                @foreach ($faqs as $faq)
                    <details class="group p-5" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium text-zinc-900">{{ $faq->question }}<flux:icon.chevron-down variant="micro" class="size-4 shrink-0 text-zinc-500 transition group-open:rotate-180" /></summary>
                        <p class="mt-3 text-sm leading-relaxed text-zinc-600">{!! nl2br(e($faq->answer)) !!}</p>
                    </details>
                @endforeach
            </div>
            <p class="mt-4 text-sm"><a href="{{ route('site.faq') }}" wire:navigate class="font-medium text-forest-700 underline">See all frequently asked questions</a></p>
        </section>
    @endif

    {{-- Closing CTA --}}
    <section class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8">
        <div class="relative isolate overflow-hidden rounded-3xl bg-forest-800 p-8 text-white sm:p-12">
            <img src="{{ asset('images/site/market-crates.jpg') }}" alt="" class="absolute inset-0 -z-10 size-full object-cover opacity-25" loading="lazy" decoding="async">
            <div class="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-2xl font-semibold sm:text-3xl">{{ C::get('home.cta_heading') }}</h2>
                    <p class="mt-2 max-w-xl text-forest-100">{{ C::get('home.cta_text') }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <flux:button variant="primary" class="!bg-gold-400 !text-forest-950 hover:!bg-gold-300" :href="auth()->check() ? route('marketplace') : route('register')" wire:navigate>{{ auth()->check() ? 'Go to marketplace' : 'Create buyer account' }}</flux:button>
                    <flux:button :href="route('site.contact')" wire:navigate>Talk to sales</flux:button>
                </div>
            </div>
        </div>
    </section>
</x-layouts.site>
