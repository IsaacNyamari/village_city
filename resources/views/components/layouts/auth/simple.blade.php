<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased">
        <div class="grid min-h-svh lg:grid-cols-2">
            <div class="relative isolate hidden flex-col justify-between overflow-hidden bg-forest-900 p-12 text-white lg:flex">
                <img src="{{ asset('images/site/hero-harvest.jpg') }}" alt="" class="absolute inset-0 -z-10 size-full object-cover opacity-50">
                <div class="absolute inset-0 -z-10 bg-gradient-to-t from-forest-950 via-forest-900/50 to-forest-900/20"></div>
                <a href="{{ route('home') }}" class="flex items-center gap-2" wire:navigate>
                    <x-app-logo />
                </a>
                <div>
                    <p class="text-4xl font-semibold leading-tight">From the farm to the city,<br><span class="text-gold-300">within 24 hours.</span></p>
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-forest-200">Agricultural aggregation, digital commerce, wholesale distribution and logistics — one platform connecting rural farmers to urban buyers across Kenya.</p>
                </div>
                <p class="text-xs text-forest-300">&copy; {{ date('Y') }} Village City</p>
            </div>

            <div class="flex flex-col items-center justify-center gap-6 p-6 md:p-10">
                <div class="flex w-full max-w-sm flex-col gap-6">
                    <a href="{{ route('home') }}" class="flex items-center gap-2 lg:hidden" wire:navigate>
                        <span class="flex size-9 items-center justify-center rounded-lg bg-forest-800 text-gold-300"><x-app-logo-icon class="size-5" /></span>
                        <span class="font-semibold tracking-wide text-forest-900">VILLAGE CITY</span>
                    </a>
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
