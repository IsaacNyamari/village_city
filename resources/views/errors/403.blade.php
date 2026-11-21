@extends('errors::layout')

@php
    $roleName = null;

    try {
        $roleName = request()->user()?->getRoleNames()->first();
    } catch (\Throwable $e) {
    }
@endphp

@section('code', '403')
@section('title', 'You don’t have access to this page')
@section('icon')
<svg viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
@endsection
@section('message')
    <p>
        @if (request()->user())
            Your account{{ $roleName ? ' ('.$roleName.')' : '' }} isn’t permitted to open this page. If you think you should have access, ask an administrator to review your role.
        @else
            You need to be signed in with the right permissions to open this page.
        @endif
    </p>
@endsection