@extends('errors::layout')

@section('code', '401')
@section('title', 'Please sign in to continue')
@section('icon')
<svg viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
@endsection
@section('message')
    <p>This page is only available to signed-in users. Log in with your Village City account and we will take you straight back.</p>
@endsection
@section('actions')
    <a href="{{ route('login') }}" class="inline-flex items-center rounded-lg bg-forest-700 px-5 py-2.5 text-sm font-medium text-white shadow-xs transition hover:bg-forest-800">Log in</a>
    <a href="{{ route('register') }}" class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-5 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50">Create buyer account</a>
@endsection