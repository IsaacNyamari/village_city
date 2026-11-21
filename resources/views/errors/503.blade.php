@extends('errors::layout')

@section('code', '503')
@section('title', 'We’ll be right back')
@section('icon')
<svg viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M2 12h4M18 12h4M4.9 19.1l2.8-2.8M16.3 7.7l2.8-2.8"/></svg>
@endsection
@section('message')
    <p>Village City is briefly offline for maintenance or an update. Orders, stock and payment records are safe. Please check back in a few minutes.</p>
@endsection
@section('actions')
    <button type="button" onclick="location.reload()" class="inline-flex items-center rounded-lg bg-forest-700 px-5 py-2.5 text-sm font-medium text-white shadow-xs transition hover:bg-forest-800">Check again</button>
@endsection