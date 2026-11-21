@props(['type' => 'bar', 'labels' => [], 'datasets' => [], 'height' => 'h-64', 'horizontal' => false, 'stacked' => false])

@php
    $hasData = collect($datasets)->flatMap(fn ($set) => $set['data'] ?? [])->contains(fn ($v) => (float) $v != 0.0);
    $config = ['type' => $type, 'labels' => collect($labels)->values()->all(), 'datasets' => $datasets, 'horizontal' => $horizontal, 'stacked' => $stacked];
@endphp

@if ($hasData)
    <div class="{{ $height }} w-full" wire:ignore wire:key="chart-{{ md5(json_encode($config)) }}">
        <canvas x-data="vcChart(@js($config))"></canvas>
    </div>
@else
    <x-ui.empty icon="chart-bar" title="No data to chart yet" text="This chart fills in as transactions are recorded." class="{{ $height }} !py-4" />
@endif
