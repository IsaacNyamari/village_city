@extends('errors::layout')

@section('code', '404')
@section('title', 'We couldn’t find that page')
@section('icon')
<svg viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5M8.5 11h5"/></svg>
@endsection
@section('message')
    <p>The page may have moved, or the link may be wrong. If you followed a link inside Village City, the record might have been removed.</p>
@endsection
@section('extra')
    <ul class="mt-6 grid gap-2 text-sm sm:grid-cols-2">
        <li><a href="{{ url('/') }}" class="font-medium text-forest-700 underline">Home page</a></li>
        @if (Route::has('site.products'))<li><a href="{{ route('site.products') }}" class="font-medium text-forest-700 underline">Browse our produce</a></li>@endif
        @if (Route::has('site.sell'))<li><a href="{{ route('site.sell') }}" class="font-medium text-forest-700 underline">Sell with us</a></li>@endif
        @if (Route::has('site.contact'))<li><a href="{{ route('site.contact') }}" class="font-medium text-forest-700 underline">Contact us</a></li>@endif
    </ul>
@endsection