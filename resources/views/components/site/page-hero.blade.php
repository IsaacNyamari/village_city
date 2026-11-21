@props(['image' => 'site/hero-harvest', 'eyebrow' => null, 'title', 'text' => null])

<section class="relative isolate overflow-hidden bg-forest-900 text-white">
    <img src="{{ asset('images/'.$image.'.jpg') }}" alt="" class="absolute inset-0 -z-10 size-full object-cover opacity-40" loading="eager">
    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-forest-950/90 via-forest-900/70 to-forest-900/20"></div>
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
        @if ($eyebrow)<p class="vc-rise text-sm font-semibold uppercase tracking-widest text-gold-300">{{ $eyebrow }}</p>@endif
        <h1 class="vc-rise mt-2 max-w-3xl text-4xl font-semibold tracking-tight sm:text-5xl">{{ $title }}</h1>
        @if ($text)<p class="vc-rise mt-4 max-w-2xl text-lg leading-relaxed text-forest-100">{{ $text }}</p>@endif
        @if (trim($slot))<div class="vc-rise mt-6">{{ $slot }}</div>@endif
    </div>
</section>
