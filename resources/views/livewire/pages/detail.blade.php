@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header :title="$d['title']" :description="$d['subtitle']" :back="$d['back']">
        @if ($d['status'])<x-ui.status :value="$d['status']" />@endif
        @if ($extra)@include($extra.'-actions')@endif
    </x-ui.header>

    @if ($d['stats'])
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($d['stats'] as $label => $stat)
                <x-ui.stat :label="$label" :value="$stat[0]" :icon="$stat[1] ?? 'chart-bar'" :tone="$stat[2] ?? 'forest'" :hint="$stat[3] ?? null" />
            @endforeach
        </div>
    @endif

    <x-ui.panel title="Details" padded><x-ui.dl :items="$d['details']" /></x-ui.panel>

    @foreach ($d['sections'] as $section)
        <x-ui.panel :title="$section['title']">
            @if (count($section['rows']) === 0)
                <x-ui.empty icon="inbox" :title="$section['empty']" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap text-sm">
                        <thead><tr class="border-b border-zinc-200 bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500">@foreach ($section['columns'] as $col)<th class="px-4 py-2.5 font-medium">{{ $col }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($section['rows'] as $row)
                                <tr class="border-b border-zinc-100">
                                    @foreach ($row as $cell)
                                        <td class="px-4 py-2">
                                            @if (is_array($cell) && isset($cell['href']))<a href="{{ $cell['href'] }}" wire:navigate class="font-medium text-forest-700 hover:underline">{{ $cell['text'] }}</a>
                                            @elseif (is_array($cell) && isset($cell['status']))<x-ui.status :value="$cell['status']" :list="$cell['list'] ?? null" />
                                            @else {{ filled($cell) || $cell === 0 ? $cell : '—' }} @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.panel>
    @endforeach

    @if ($extra)@include($extra)@endif
</div>
