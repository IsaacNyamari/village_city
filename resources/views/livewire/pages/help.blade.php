@php use Illuminate\Support\Str; @endphp
<div class="space-y-6">
    <x-ui.header image="site/market-stall" title="Help & Documentation"
        :description="$role ? 'Guides for '.$role.' and everyone at Village City.' : 'Guides for using Village City.'">
        <div class="w-64 max-sm:w-full"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search the guides…" clearable /></div>
    </x-ui.header>

    <div class="grid gap-6 lg:grid-cols-[17rem_minmax(0,1fr)]">
        {{-- Guide list --}}
        <nav aria-label="Guides">
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs lg:sticky lg:top-20">
                <a href="{{ route('help.index') }}" wire:navigate @class(['flex items-center gap-3 border-b border-zinc-100 px-4 py-3 text-sm font-medium', 'bg-forest-50 text-forest-800' => $doc === null && $search === '', 'text-zinc-700 hover:bg-zinc-50' => ! ($doc === null && $search === '')])>
                    <flux:icon.rocket-launch variant="mini" class="size-4 text-forest-600" /> Your quick start
                </a>
                @foreach ($available as $slug => $d)
                    <a href="{{ route('help.show', $slug) }}" wire:navigate @class(['flex items-center gap-3 border-b border-zinc-100 px-4 py-3 text-sm font-medium last:border-0', 'bg-forest-50 text-forest-800' => $doc === $slug, 'text-zinc-700 hover:bg-zinc-50' => $doc !== $slug])>
                        <flux:icon :name="$d['icon']" variant="mini" class="size-4 shrink-0 text-forest-600" />
                        <span class="min-w-0"><span class="block leading-snug">{{ $d['title'] }}</span></span>
                    </a>
                @endforeach
            </div>
        </nav>

        <div class="min-w-0 space-y-6">
            @if ($search !== '')
                <x-ui.panel :title="'Search results for “'.$search.'”'">
                    @forelse ($results as $r)
                        <div class="border-b border-zinc-100 px-5 py-4 last:border-0">
                            <a href="{{ route('help.show', $r['slug']) }}" wire:navigate class="font-semibold text-forest-700 hover:underline">{{ $r['title'] }}</a>
                            <ul class="mt-2 space-y-1.5">
                                @foreach ($r['hits'] as $hit)
                                    <li class="text-sm text-zinc-600"><a href="{{ route('help.show', $r['slug']) }}#{{ Str::slug($hit['heading']) }}" wire:navigate class="font-medium text-zinc-800 hover:underline">{{ $hit['heading'] }}</a> — {{ $hit['text'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <x-ui.empty icon="magnifying-glass" title="No matches" text="Try different words, or browse the guides on the left." />
                    @endforelse
                </x-ui.panel>
            @elseif ($doc === null)
                {{-- Landing: role quick start + guide cards --}}
                @if ($quickStart)
                    <x-ui.panel :title="'Your quick start — '.$role" padded>
                        <div class="prose-vc">{!! $quickStart !!}</div>
                        <p class="mt-4 text-xs text-zinc-500">Other roles are described in <a href="{{ route('help.show', 'roles') }}" wire:navigate class="underline">Roles & responsibilities</a>.</p>
                    </x-ui.panel>
                @else
                    <flux:callout variant="secondary" icon="information-circle" heading="Welcome to Village City">
                        <flux:callout.text>No role is assigned to your account yet, so most pages are hidden. Ask an administrator to assign you a role. The guides below are available to you now.</flux:callout.text>
                    </flux:callout>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($available as $slug => $d)
                        <a href="{{ route('help.show', $slug) }}" wire:navigate class="group rounded-xl border border-zinc-200 bg-white p-5 shadow-xs transition hover:border-forest-300 hover:shadow-md">
                            <span class="flex size-10 items-center justify-center rounded-lg bg-forest-50 text-forest-700"><flux:icon :name="$d['icon']" class="size-5" /></span>
                            <h3 class="mt-3 font-semibold text-zinc-900 group-hover:text-forest-800">{{ $d['title'] }}</h3>
                            <p class="mt-1 text-sm text-zinc-600">{{ $d['summary'] }}</p>
                            <p class="mt-2 text-xs text-zinc-400">For: {{ $d['audience'] }}</p>
                        </a>
                    @endforeach
                </div>

                <x-ui.panel padded>
                    <p class="text-sm text-zinc-600">Can't find what you need? Ask your supervisor, or use the <a class="font-medium text-forest-700 underline" href="{{ route('site.contact') }}">Contact page</a> on the website.</p>
                </x-ui.panel>
            @else
                {{-- A guide --}}
                <x-ui.panel padded>
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-zinc-100 pb-4">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-zinc-500">{{ $currentDoc['audience'] }}</p>
                            <p class="text-sm text-zinc-600">{{ $currentDoc['summary'] }}</p>
                        </div>
                        <flux:button size="sm" icon="printer" onclick="window.print()">Print</flux:button>
                    </div>

                    @if (count($content['toc']) > 2)
                        <details class="mb-6 rounded-lg bg-zinc-50 p-4" open>
                            <summary class="cursor-pointer text-sm font-semibold text-zinc-800">On this page</summary>
                            <ul class="mt-3 grid gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
                                @foreach ($content['toc'] as $item)
                                    <li @class(['pl-4' => $item['level'] === 3])><a href="#{{ $item['id'] }}" class="text-forest-700 hover:underline">{{ $item['text'] }}</a></li>
                                @endforeach
                            </ul>
                        </details>
                    @endif

                    <article class="prose-vc">{!! $content['html'] !!}</article>
                </x-ui.panel>
            @endif
        </div>
    </div>
</div>
