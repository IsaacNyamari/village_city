@extends('errors::layout')

@section('code', '419')
@section('title', 'Your session has expired')
@section('icon')
<svg viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
@endsection
@section('message')
    <p>For your security, pages expire after a period of inactivity. Nothing was lost on our side — reload the page and try again. If you were signing in, simply sign in again.</p>
@endsection
@section('actions')
    <button type="button" onclick="location.reload()" class="inline-flex items-center rounded-lg bg-forest-700 px-5 py-2.5 text-sm font-medium text-white shadow-xs transition hover:bg-forest-800">Reload the page</button>
@endsection