@props(['seo' => null, 'title' => null, 'description' => null])
@php
    use App\Support\SiteContent;

    // Pages pass a full SEO payload; fall back to a minimal one so the layout never breaks.
    $seo ??= \App\Support\Seo::build(['title' => $title ?: 'Village City', 'description' => $description]);
    $links = [['Home', route('home'), 'home'], ['Products', route('site.products'), 'site.products*'], ['Sell with us', route('site.sell'), 'site.sell'], ['About', route('site.about'), 'site.about'], ['FAQ', route('site.faq'), 'site.faq'], ['Contact', route('site.contact'), 'site.contact']];
    $company = \App\Models\Setting::get('company_name', 'Village City');
    $socials = array_filter([
        'Facebook' => \App\Support\Seo::setting('social_facebook'), 'X' => \App\Support\Seo::setting('social_x'), 'Instagram' => \App\Support\Seo::setting('social_instagram'),
        'LinkedIn' => \App\Support\Seo::setting('social_linkedin'), 'YouTube' => \App\Support\Seo::setting('social_youtube'),
    ]);
    $footerCategories = \App\Models\ProductCategory::where('status', 'active')->orderBy('name')->get(['name', 'slug']);
@endphp
<!DOCTYPE html>
<html lang="en-KE">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <x-seo.head :seo="$seo" />
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
    </head>
    <body class="min-h-screen bg-white text-zinc-800 antialiased">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-forest-800 focus:px-4 focus:py-2 focus:text-white">Skip to main content</a>

        @if (SiteContent::enabled('site.announcement_enabled') && SiteContent::get('site.announcement_text') !== '')
            <div class="bg-gold-400 px-4 py-2 text-center text-sm font-medium text-forest-950" role="status">
                @if ($url = SiteContent::get('site.announcement_link'))
                    <a href="{{ $url }}" class="underline underline-offset-2">{{ SiteContent::get('site.announcement_text') }}</a>
                @else
                    {{ SiteContent::get('site.announcement_text') }}
                @endif
            </div>
        @endif

        <header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-zinc-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-6 px-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2" wire:navigate aria-label="{{ $company }} home">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-forest-800 text-gold-300"><x-app-logo-icon class="size-5" /></span>
                    <span class="font-semibold tracking-wide text-forest-900">VILLAGE CITY</span>
                </a>

                <nav class="hidden items-center gap-1 lg:flex" aria-label="Main">
                    @foreach ($links as [$label, $url, $name])
                        <a href="{{ $url }}" wire:navigate @class(['rounded-lg px-3 py-2 text-sm font-medium', 'bg-forest-50 text-forest-800' => request()->routeIs($name), 'text-zinc-600 hover:text-forest-800' => ! request()->routeIs($name)]) @if (request()->routeIs($name)) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>

                <div class="hidden items-center gap-2 lg:flex">
                    <livewire:cart-button />
                    @auth
                        <flux:button size="sm" variant="primary" :href="route('dashboard')" wire:navigate>{{ auth()->user()->can('dashboard.view') ? 'Dashboard' : 'Marketplace' }}</flux:button>
                    @else
                        <flux:button size="sm" variant="ghost" :href="route('login')" wire:navigate>Log in</flux:button>
                        <flux:button size="sm" variant="primary" :href="route('register')" wire:navigate>Sign up to order</flux:button>
                    @endauth
                </div>

                <div class="flex items-center gap-2 lg:hidden"><livewire:cart-button /></div>
                <button type="button" class="rounded-lg p-2 text-zinc-700 hover:bg-zinc-100 lg:hidden" @click="open = !open" aria-label="Toggle menu" :aria-expanded="open">
                    <flux:icon.bars-3 x-show="!open" /><flux:icon.x-mark x-show="open" x-cloak />
                </button>
            </div>

            <div x-show="open" x-cloak class="border-t border-zinc-200 bg-white px-4 pb-4 pt-2 lg:hidden">
                @foreach ($links as [$label, $url, $name])
                    <a href="{{ $url }}" wire:navigate class="block rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-700 hover:bg-forest-50">{{ $label }}</a>
                @endforeach
                <div class="mt-3 flex gap-2">
                    @auth
                        <flux:button class="flex-1" variant="primary" :href="route('dashboard')" wire:navigate>Open app</flux:button>
                    @else
                        <flux:button class="flex-1" :href="route('login')" wire:navigate>Log in</flux:button>
                        <flux:button class="flex-1" variant="primary" :href="route('register')" wire:navigate>Sign up</flux:button>
                    @endauth
                </div>
            </div>
        </header>

        <main id="main">{{ $slot }}</main>

        <footer class="mt-24 bg-forest-950 text-forest-200">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-4 lg:px-8">
                <div>
                    <div class="flex items-center gap-2 text-white">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-gold-400 text-forest-950"><x-app-logo-icon class="size-5" /></span>
                        <span class="font-semibold tracking-wide">VILLAGE CITY</span>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-forest-300">{{ \App\Models\Setting::get('company_tagline') }} {{ SiteContent::get('site.footer_tagline') }}</p>
                    @if ($socials)
                        <ul class="mt-4 flex flex-wrap gap-3 text-sm">
                            @foreach ($socials as $name => $url)<li><a href="{{ $url }}" target="_blank" rel="noopener me" class="underline hover:text-gold-300">{{ $name }}</a></li>@endforeach
                        </ul>
                    @endif
                </div>
                <nav aria-label="Explore">
                    <p class="text-sm font-semibold text-white">Explore</p>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($links as [$label, $url])<li><a href="{{ $url }}" wire:navigate class="hover:text-gold-300">{{ $label }}</a></li>@endforeach
                        <li><a href="{{ route('login') }}" wire:navigate class="hover:text-gold-300">Customer login</a></li>
                    </ul>
                </nav>
                <nav aria-label="Product categories">
                    <p class="text-sm font-semibold text-white">Our produce</p>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($footerCategories as $category)<li><a href="{{ route('site.category', $category->slug) }}" wire:navigate class="hover:text-gold-300">{{ $category->name }}</a></li>@endforeach
                        <li><a href="{{ route('site.products') }}" wire:navigate class="hover:text-gold-300">All products</a></li>
                    </ul>
                </nav>
                <div>
                    <p class="text-sm font-semibold text-white">Contact</p>
                    <address class="mt-3 space-y-2 text-sm not-italic">
                        @if ($phone = \App\Models\Setting::get('company_phone'))<p><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="hover:text-gold-300">{{ $phone }}</a></p>@endif
                        @if ($email = \App\Models\Setting::get('company_email'))<p><a href="mailto:{{ $email }}" class="hover:text-gold-300">{{ $email }}</a></p>@endif
                        @if ($address = \App\Models\Setting::get('company_address'))<p>{{ $address }}</p>@endif
                        <p><a href="{{ route('site.contact') }}" wire:navigate class="text-gold-300 hover:underline">Send us a message →</a></p>
                    </address>
                </div>
            </div>
            <div class="border-t border-forest-900 py-5 text-center text-xs text-forest-400">&copy; {{ date('Y') }} {{ $company }}. All rights reserved. · <a href="{{ url('sitemap.xml') }}" class="hover:text-gold-300">Sitemap</a> · Photography from <a href="https://www.pexels.com" target="_blank" rel="noopener" class="underline hover:text-gold-300">Pexels</a>.</div>
        </footer>
        <flux:toast position="top end" />
        @fluxScripts
    </body>
</html>
