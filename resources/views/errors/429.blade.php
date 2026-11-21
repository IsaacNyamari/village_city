@extends('errors::layout')

@php
    $wait = (int) ($exception->getHeaders()['Retry-After'] ?? 0);
@endphp

@section('code', '429')
@section('title', 'Too many requests')
@section('icon')
<svg viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2.5 20h19L12 3Z"/><path d="M12 10v4M12 17h.01"/></svg>
@endsection
@section('message')
    <p>You have made too many attempts in a short time. Please wait @if ($wait > 0)about <strong>{{ $wait >= 60 ? ceil($wait / 60).' minute(s)' : $wait.' second(s)' }}</strong>@else a few minutes @endif and try again.</p>
@endsection
@section('actions')
    <button type="button" onclick="location.reload()" class="inline-flex items-center rounded-lg bg-forest-700 px-5 py-2.5 text-sm font-medium text-white shadow-xs transition hover:bg-forest-800">Try again</button>
@endsection