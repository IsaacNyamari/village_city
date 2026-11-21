<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ filled($title ?? null) ? $title.' · ' : '' }}{{ config('app.name', 'Village City') }}</title>
{{-- The operations app and sign-in pages are never indexed. --}}
<meta name="robots" content="noindex, nofollow, noarchive">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="theme-color" content="#133726">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])