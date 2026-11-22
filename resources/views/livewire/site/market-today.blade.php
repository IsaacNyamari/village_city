@php use App\Support\Options; @endphp
<div>
    <x-site.page-hero image="site/market-stall" eyebrow="{{ now()->format('l, d M') }}" title="Today's market" text="What each trading centre has on offer today. Order before the centre's cut-off." />

    <section class="mx-auto max-w-5xl space-y-6 px-4 py-10 sm:px-6 lg:px-8">
        @forelse ($groups as $rows)
            @php $centre = $rows->first()->centre; @endphp
            <div class="rounded-2xl border border-zinc-200">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200 p-4">
                    <h2 class="font-semibold text-zinc-900">{{ $centre->name }}<span class="font-normal text-zinc-500"> · {{ $centre->county?->name }}</span></h2>
                    <span class="text-xs text-zinc-500">Market days: {{ $centre->market_days ?: 'Daily' }} · Order by {{ \Illuminate\Support\Carbon::parse($centre->order_cutoff_time)->format('H:i') }}</span>
                </div>
                <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($rows as $l)
                        <a href="{{ route('site.product', $l->product) }}" class="rounded-xl border border-zinc-100 p-3 hover:border-forest-300">
                            <div class="font-medium text-zinc-900">{{ $l->product->name }}</div>
                            <div class="text-sm text-zinc-600">{{ Options::money($l->unit_price) }} / {{ $l->product->unit }} · {{ Options::qty($l->quantity_available) }} available</div>
                            <div class="text-xs text-zinc-500">Min. order {{ Options::qty($l->product->min_order_qty) }}</div>
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-zinc-300 p-10 text-center text-sm text-zinc-500">Today's listings open shortly after midnight on market days. Please check back soon.</p>
        @endforelse
    </section>
</div>
