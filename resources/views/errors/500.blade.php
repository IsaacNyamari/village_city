@extends('errors::layout')

@section('code', '500')
@section('title', 'Something went wrong on our side')
@section('icon')
<svg viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4 2.6-2.6Z"/></svg>
@endsection
@section('message')
    <p>An unexpected error stopped this page from loading. It has been recorded and we are looking into it. Your data has not been affected.</p>
    <p>Please try again in a moment. If the problem continues, tell your administrator what you were doing when it happened.</p>
@endsection
@section('actions')
    <button type="button" onclick="location.reload()" class="inline-flex items-center rounded-lg bg-forest-700 px-5 py-2.5 text-sm font-medium text-white shadow-xs transition hover:bg-forest-800">Try again</button>
@endsection