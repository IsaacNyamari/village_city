@php use App\Support\SiteContent as C; @endphp
<x-layouts.site :seo="$seo">
    <x-site.page-hero image="site/hero-farmer" eyebrow="About Village City" :title="C::get('about.hero_title')" :text="C::get('about.hero_text')" />
    <x-site.breadcrumbs :items="[['Home', route('home')], ['About us', null]]" />

    <section class="mx-auto max-w-4xl space-y-12 px-4 py-10 sm:px-6 lg:px-8">
        <div class="grid items-center gap-8 md:grid-cols-2">
            <img src="{{ asset('images/site/market-crates.jpg') }}" alt="Fresh produce stacked in crates at a market" width="900" height="600" class="h-64 w-full rounded-2xl object-cover md:order-2" loading="lazy" decoding="async">
            <div class="space-y-6 md:order-1">
                <div>
                    <h2 class="text-xl font-semibold text-zinc-900">Our mission</h2>
                    <p class="mt-3 leading-relaxed text-zinc-600">{{ C::get('about.mission') }}</p>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-zinc-900">Our vision</h2>
                    <p class="mt-3 leading-relaxed text-zinc-600">{{ C::get('about.vision') }}</p>
                </div>
            </div>
        </div>

        <div>
            <h2 class="text-xl font-semibold text-zinc-900">What we do</h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach (C::lines('about.what_we_do') as $item)
                    <li class="flex gap-3 rounded-xl border border-zinc-200 p-4 text-sm text-zinc-700"><flux:icon.check-circle class="size-5 shrink-0 text-forest-600" />{{ $item }}</li>
                @endforeach
            </ul>
        </div>

        <div>
            <h2 class="text-xl font-semibold text-zinc-900">Our values</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                @foreach (C::pairs('about.values') as [$t, $d])
                    <div class="rounded-xl bg-forest-50 p-5"><h3 class="font-semibold text-forest-900">{{ $t }}</h3><p class="mt-1 text-sm text-zinc-600">{{ $d }}</p></div>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 p-6 text-center">
            <h2 class="text-lg font-semibold text-zinc-900">Want to work with us?</h2>
            <p class="mt-1 text-sm text-zinc-600">Farmers, cooperatives, buyers and partners are all welcome.</p>
            <div class="mt-4 flex flex-wrap justify-center gap-3">
                <flux:button variant="primary" :href="route('site.contact')" wire:navigate>Get in touch</flux:button>
                <flux:button :href="route('site.sell')" wire:navigate>Sell with us</flux:button>
            </div>
        </div>
    </section>
</x-layouts.site>
