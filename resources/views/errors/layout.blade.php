{{--
    Shared layout for all error pages. Deliberately self-contained: no database, Livewire or
    session-dependent components, so it still renders when the application is unhealthy.
    Usage: @extends('errors::layout') with sections: code, title, message, and optionally icon, actions, extra.
--}}
@php
    $appName = config('app.name', 'Village City');
    $user = null;

    try {
        $user = request()->user();
    } catch (\Throwable $e) {
        // Session or auth unavailable (e.g. during maintenance): treat as a guest.
    }

    $home = $user ? (\Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/')) : url('/');
    $hasAssets = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>@yield('code') · @yield('title') — {{ $appName }}</title>
    @if ($hasAssets)
        @vite('resources/css/app.css')
    @endif
    <style>
        /* Fallback so the page is still readable if the compiled stylesheet is missing. */
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-800 antialiased">
    <div class="grid min-h-screen lg:grid-cols-5">
        {{-- Photo panel --}}
        <aside class="relative isolate hidden overflow-hidden bg-forest-900 text-white lg:col-span-2 lg:flex lg:flex-col lg:justify-between lg:p-12">
            <img src="{{ asset('images/site/hero-harvest.jpg') }}" alt="" class="absolute inset-0 -z-10 size-full object-cover opacity-45">
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-forest-950 via-forest-900/60 to-forest-900/30"></div>

            <a href="{{ url('/') }}" class="flex items-center gap-2">
                <span class="flex size-10 items-center justify-center rounded-lg bg-gold-400 text-forest-950">
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21V11"/><path d="M12 11c0-4 2.5-7 7-7 0 4.5-2.5 7-7 7Z"/><path d="M12 15c0-3-2-5.5-6-5.5 0 3.5 2 5.5 6 5.5Z"/><path d="M5 21h14"/></svg>
                </span>
                <span class="font-semibold tracking-wide">VILLAGE CITY</span>
            </a>

            <div>
                <p class="select-none text-[9rem] font-bold leading-none tracking-tighter text-white/15">@yield('code')</p>
                <p class="mt-2 text-2xl font-semibold leading-snug">From the farm to the city,<br><span class="text-gold-300">within 24 hours.</span></p>
            </div>

            <p class="text-xs text-forest-300">&copy; {{ date('Y') }} {{ $appName }}</p>
        </aside>

        {{-- Message --}}
        <main class="flex flex-col justify-center px-6 py-12 sm:px-12 lg:col-span-3 lg:px-20">
            <a href="{{ url('/') }}" class="mb-10 flex items-center gap-2 lg:hidden">
                <span class="flex size-9 items-center justify-center rounded-lg bg-forest-800 text-gold-300">
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21V11"/><path d="M12 11c0-4 2.5-7 7-7 0 4.5-2.5 7-7 7Z"/><path d="M12 15c0-3-2-5.5-6-5.5 0 3.5 2 5.5 6 5.5Z"/><path d="M5 21h14"/></svg>
                </span>
                <span class="font-semibold tracking-wide text-forest-900">VILLAGE CITY</span>
            </a>

            <div class="max-w-xl">
                <span class="flex size-14 items-center justify-center rounded-2xl bg-forest-100 text-forest-700">
                    @hasSection('icon')
                        @yield('icon')
                    @else
                        <svg viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
                    @endif
                </span>

                <p class="mt-6 text-sm font-semibold uppercase tracking-widest text-forest-600">Error @yield('code')</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-zinc-900 sm:text-4xl">@yield('title')</h1>
                <div class="mt-4 space-y-3 text-base leading-relaxed text-zinc-600">@yield('message')</div>

                @yield('extra')

                <div class="mt-8 flex flex-wrap gap-3">
                    @hasSection('actions')
                        @yield('actions')
                    @else
                        <a href="{{ $home }}" class="inline-flex items-center rounded-lg bg-forest-700 px-5 py-2.5 text-sm font-medium text-white shadow-xs transition hover:bg-forest-800">{{ $user ? 'Go to my dashboard' : 'Back to home' }}</a>
                    @endif
                    <button type="button" onclick="history.length > 1 ? history.back() : (location.href = '{{ $home }}')" class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-5 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50">Go back</button>
                </div>

                <p class="mt-10 text-sm text-zinc-500">
                    @if ($user && \Illuminate\Support\Facades\Route::has('help.index'))
                        Need a hand? Open <a href="{{ route('help.index') }}" class="font-medium text-forest-700 underline">Help &amp; Documentation</a>.
                    @elseif (\Illuminate\Support\Facades\Route::has('site.contact'))
                        Need a hand? <a href="{{ route('site.contact') }}" class="font-medium text-forest-700 underline">Contact us</a>.
                    @endif
                </p>
            </div>
        </main>
    </div>
</body>
</html>
