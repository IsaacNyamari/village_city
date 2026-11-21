@props(['seo'])
@php
    $og = $seo['og'];
    $tw = $seo['twitter'];
@endphp
<title>{{ $seo['title'] }}</title>
<meta name="description" content="{{ $seo['description'] }}">
<meta name="robots" content="{{ $seo['robots'] }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">
<link rel="alternate" hreflang="en-KE" href="{{ $seo['canonical'] }}">
<link rel="alternate" hreflang="x-default" href="{{ $seo['canonical'] }}">

{{-- Open Graph (Facebook, WhatsApp, LinkedIn, Slack …) --}}
<meta property="og:type" content="{{ $og['type'] }}">
<meta property="og:site_name" content="{{ $seo['site_name'] }}">
<meta property="og:locale" content="{{ $seo['locale'] }}">
<meta property="og:title" content="{{ $og['title'] }}">
<meta property="og:description" content="{{ $og['description'] }}">
<meta property="og:url" content="{{ $og['url'] }}">
@if ($og['image'])
    <meta property="og:image" content="{{ $og['image'] }}">
    @if (str_starts_with($og['image'], 'https://'))<meta property="og:image:secure_url" content="{{ $og['image'] }}">@endif
    @if ($og['image_width'])<meta property="og:image:width" content="{{ $og['image_width'] }}">@endif
    @if ($og['image_height'])<meta property="og:image:height" content="{{ $og['image_height'] }}">@endif
    <meta property="og:image:alt" content="{{ $og['image_alt'] }}">
@endif
@if (! empty($og['product']))
    <meta property="product:price:amount" content="{{ $og['product']['price'] }}">
    <meta property="product:price:currency" content="KES">
    <meta property="product:availability" content="{{ $og['product']['availability'] }}">
    @if (! empty($og['product']['category']))<meta property="product:category" content="{{ $og['product']['category'] }}">@endif
@endif

{{-- X / Twitter --}}
<meta name="twitter:card" content="{{ $tw['card'] }}">
@if ($tw['site'])<meta name="twitter:site" content="{{ $tw['site'] }}"><meta name="twitter:creator" content="{{ $tw['site'] }}">@endif
<meta name="twitter:title" content="{{ $tw['title'] }}">
<meta name="twitter:description" content="{{ $tw['description'] }}">
@if ($tw['image'])<meta name="twitter:image" content="{{ $tw['image'] }}"><meta name="twitter:image:alt" content="{{ $tw['image_alt'] }}">@endif

{{-- Search console verification --}}
@if ($seo['verification']['google'])<meta name="google-site-verification" content="{{ $seo['verification']['google'] }}">@endif
@if ($seo['verification']['bing'])<meta name="msvalidate.01" content="{{ $seo['verification']['bing'] }}">@endif

{{-- Icons, manifest, colours --}}
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/icon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('icons/icon-16.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="theme-color" content="#133726">
<meta name="application-name" content="{{ $seo['site_name'] }}">
<meta name="apple-mobile-web-app-title" content="{{ $seo['site_name'] }}">
<meta name="format-detection" content="telephone=no">

@if ($seo['preload'])<link rel="preload" as="image" href="{{ $seo['preload'] }}" fetchpriority="high">@endif

{{-- Structured data --}}
@foreach ($seo['schema'] as $node)
    <script type="application/ld+json">{!! \App\Support\Schema::encode($node) !!}</script>
@endforeach

{{-- Analytics: public visitors only, never signed-in staff --}}
@if ($seo['analytics'] && ! auth()->check())
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $seo['analytics'] }}"></script>
    <script>window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date()); gtag('config', '{{ $seo['analytics'] }}', { anonymize_ip: true });</script>
@endif
