@php
    use App\Support\Seo as SeoHelper;
    use Illuminate\Support\Str;

    $lenClass = fn (int $len, int $min, int $max) => $len === 0 ? 'text-zinc-400' : ($len >= $min && $len <= $max ? 'text-forest-700' : 'text-gold-600');
@endphp
<div class="space-y-6">
    <x-ui.header image="site/market-crates" title="SEO & Site Content"
        description="Manage how Village City appears in search engines and social media, edit the public website's copy and FAQs, and check SEO health.">
        <flux:button icon="arrow-top-right-on-square" :href="route('home')" target="_blank">View website</flux:button>
    </x-ui.header>

    {{-- Tabs --}}
    <div class="flex flex-wrap gap-2 border-b border-zinc-200" role="tablist">
        @foreach ($tabs as $key => $label)
            <button type="button" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $key }}')"
                @class(['-mb-px border-b-2 px-4 pb-2.5 text-sm font-medium', 'border-forest-700 text-forest-800' => $tab === $key, 'border-transparent text-zinc-500 hover:text-zinc-800' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </div>

    {{-- ================= OVERVIEW ================= --}}
    @if ($tab === 'overview')
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.stat label="SEO health score" :value="$audit['score'].' / 100'" icon="chart-bar" :tone="$audit['score'] >= 80 ? 'forest' : ($audit['score'] >= 50 ? 'gold' : 'red')" hint="Technical + content checks" />
            <x-ui.stat label="Issues to fix" :value="$audit['counts']['bad']" icon="exclamation-circle" :tone="$audit['counts']['bad'] ? 'red' : 'forest'" hint="Critical" />
            <x-ui.stat label="Improvements" :value="$audit['counts']['warn']" icon="light-bulb" tone="gold" hint="Recommended" />
            <x-ui.stat label="URLs in sitemap" :value="$audit['urls']" icon="globe-alt" tone="blue" />
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <x-ui.panel title="Health check" description="Fix red items first, then amber." class="lg:col-span-2">
                @foreach ($audit['checks'] as $check)
                    <div class="flex items-start gap-3 border-b border-zinc-100 px-5 py-3 last:border-0">
                        @php $icons = ['ok' => ['check-circle', 'text-forest-600'], 'warn' => ['exclamation-triangle', 'text-gold-500'], 'bad' => ['x-circle', 'text-red-600'], 'info' => ['information-circle', 'text-sky-600']][$check['status']]; @endphp
                        <flux:icon :name="$icons[0]" class="mt-0.5 size-5 shrink-0 {{ $icons[1] }}" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-zinc-900">{{ $check['title'] }}</p>
                            <p class="text-sm text-zinc-600">{{ $check['detail'] }}</p>
                        </div>
                        @if ($check['status'] !== 'ok' && $check['tab'] !== 'overview')
                            <flux:button size="xs" wire:click="$set('tab', '{{ $check['tab'] }}')">Fix</flux:button>
                        @endif
                    </div>
                @endforeach
            </x-ui.panel>

            <div class="space-y-6">
                <x-ui.panel title="AI assistant (Google Gemini)" padded>
                    @if ($aiReady)
                        <p class="text-sm text-zinc-600">Connected. Look for the <span class="inline-flex items-center gap-0.5 font-medium text-forest-700"><flux:icon.sparkles variant="micro" class="size-3.5" /> sparkle</span> buttons to draft titles, descriptions, product copy, FAQs and keywords. Suggestions are never saved automatically.</p>
                        <p class="mt-2 text-xs text-zinc-500">Models tried in order: {{ implode(', ', $aiModels) }}</p>
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <x-ui.ai-button target="aiTest" wire:click="aiTest">Test connection</x-ui.ai-button>
                            @if ($aiStatus)
                                <span class="text-xs {{ $aiStatus['ok'] ? 'text-forest-700' : 'text-red-600' }}">{{ $aiStatus['ok'] ? 'OK — answered by '.$aiStatus['model'] : 'Not working — see the message shown.' }}</span>
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-zinc-600">Not configured. Add <code class="rounded bg-zinc-100 px-1">GEMINI_API_KEY</code> (and optionally <code class="rounded bg-zinc-100 px-1">GEMINI_MODELS</code>) to the <code class="rounded bg-zinc-100 px-1">.env</code> file, then run <code class="rounded bg-zinc-100 px-1">php artisan config:clear</code>.</p>
                    @endif
                </x-ui.panel>
                <x-ui.panel title="Search engine files" padded>
                    <ul class="space-y-2 text-sm">
                        <li><a class="font-medium text-forest-700 underline" target="_blank" href="{{ route('seo.sitemap') }}">sitemap.xml</a> <span class="text-zinc-500">— pages, categories, products and images</span></li>
                        <li><a class="font-medium text-forest-700 underline" target="_blank" href="{{ route('seo.robots') }}">robots.txt</a> <span class="text-zinc-500">— crawl rules and sitemap link</span></li>
                        <li><a class="font-medium text-forest-700 underline" target="_blank" href="{{ asset('site.webmanifest') }}">site.webmanifest</a> <span class="text-zinc-500">— app name and icons</span></li>
                    </ul>
                </x-ui.panel>
                <x-ui.panel title="Test your pages" padded>
                    <p class="mb-2 text-xs text-zinc-500">Use these free tools once the site is live at <strong>{{ $baseUrl }}</strong>:</p>
                    <ul class="space-y-2 text-sm">
                        <li><a class="text-forest-700 underline" target="_blank" rel="noopener" href="https://search.google.com/test/rich-results">Google Rich Results Test</a></li>
                        <li><a class="text-forest-700 underline" target="_blank" rel="noopener" href="https://developers.facebook.com/tools/debug/">Facebook Sharing Debugger</a></li>
                        <li><a class="text-forest-700 underline" target="_blank" rel="noopener" href="https://search.google.com/search-console">Google Search Console</a></li>
                        <li><a class="text-forest-700 underline" target="_blank" rel="noopener" href="https://pagespeed.web.dev/">PageSpeed Insights</a></li>
                        <li><a class="text-forest-700 underline" target="_blank" rel="noopener" href="https://www.linkedin.com/post-inspector/">LinkedIn Post Inspector</a></li>
                    </ul>
                </x-ui.panel>
            </div>
        </div>
    @endif

    {{-- ================= GLOBAL ================= --}}
    @if ($tab === 'global')
        <form wire:submit="saveGlobal" class="space-y-6">
            @php
                $groups = [
                    'Brand & defaults' => ['site_name', 'title_template', 'default_description', 'default_og_image', 'logo', 'locale'],
                    'Indexing' => ['allow_indexing', 'canonical_base', 'robots_extra'],
                    'Search consoles & analytics' => ['google_site_verification', 'bing_site_verification', 'ga4_id'],
                    'Organization (structured data)' => ['org_legal_name', 'org_founding_year', 'org_area_served'],
                    'Social profiles' => ['twitter_handle', 'social_facebook', 'social_x', 'social_instagram', 'social_linkedin', 'social_youtube'],
                ];
            @endphp
            @foreach ($groups as $title => $keys)
                <x-ui.panel :title="$title" padded>
                    <div class="grid gap-5 lg:grid-cols-2">
                        @foreach ($keys as $key)
                            @php $def = config('site.global.'.$key); @endphp
                            <div class="{{ in_array($def['type'], ['textarea', 'image', 'switch']) ? 'lg:col-span-2' : '' }}" wire:key="g-{{ $key }}">
                                @include('livewire.pages.seo-field', ['model' => 'global.'.$key, 'field' => $def, 'fileKey' => 'g__'.$key, 'reset' => "resetImage('g', '{$key}')"])
                                @if ($key === 'default_description' && $aiReady)
                                    <div class="mt-2"><x-ui.ai-button target="aiGlobal" wire:click="aiGlobal">Suggest with AI</x-ui.ai-button></div>
                                @endif
                                <flux:error name="global.{{ $key }}" />
                            </div>
                        @endforeach
                    </div>
                </x-ui.panel>
            @endforeach
            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveGlobal">Save global SEO</flux:button>
                <span class="text-xs text-zinc-500">Business phone, email and address are managed in Administration → Settings.</span>
            </div>
        </form>
    @endif

    {{-- ================= PAGES ================= --}}
    @if ($tab === 'pages')
        <div class="grid gap-6 lg:grid-cols-[14rem_minmax(0,1fr)]">
            <nav aria-label="Pages" class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs lg:self-start">
                @foreach (config('site.pages') as $key => $def)
                    <button type="button" wire:click="$set('pageKey', '{{ $key }}')" @class(['block w-full border-b border-zinc-100 px-4 py-3 text-left text-sm font-medium last:border-0', 'bg-forest-50 text-forest-800' => $pageKey === $key, 'text-zinc-700 hover:bg-zinc-50' => $pageKey !== $key])>{{ $def['label'] }}<span class="block text-xs font-normal text-zinc-500">{{ $def['path'] }}</span></button>
                @endforeach
            </nav>

            @php
                $pk = $pageKey;
                $pdef = config('site.pages.'.$pk);
                $pv = $pages[$pk];
                $fullTitle = SeoHelper::title((string) $pv['title']);
                $tLen = Str::length($fullTitle);
                $dLen = Str::length((string) $pv['description']);
                $upload = $files['p__'.$pk.'__og_image'] ?? null;
                $ogImg = $upload ? $upload->temporaryUrl() : SeoHelper::mediaUrl($pv['og_image']);
            @endphp
            <form wire:submit="savePage('{{ $pk }}')" class="space-y-6" wire:key="page-{{ $pk }}">
                <x-ui.panel :title="$pdef['label'].' — search result'" description="Live preview of how this page may look on Google." padded>
                    <div class="max-w-2xl rounded-lg border border-zinc-200 bg-white p-4">
                        <p class="truncate text-xs text-zinc-600">{{ $baseUrl }}{{ $pdef['path'] === '/' ? '' : $pdef['path'] }}</p>
                        <p class="mt-0.5 truncate text-xl text-[#1a0dab]">{{ Str::limit($fullTitle, 62) }}</p>
                        <p class="mt-1 line-clamp-2 text-sm text-zinc-600">{{ Str::limit((string) $pv['description'], 165) }}</p>
                    </div>

                    @if ($aiReady)
                        <div class="mt-5 rounded-lg border border-forest-100 bg-forest-50/60 p-4">
                            <p class="text-sm font-medium text-forest-900"><flux:icon.sparkles variant="micro" class="mr-1 inline size-4" />AI assistant</p>
                            <div class="mt-3 flex flex-wrap items-end gap-3">
                                <div class="min-w-56 flex-1"><flux:input wire:model="aiKeywords.{{ $pk }}" label="Target keywords (optional)" placeholder="wholesale vegetables kenya, bulk produce" /></div>
                                <x-ui.ai-button target="aiPage" wire:click="aiPage('{{ $pk }}')">Suggest title, description &amp; social text</x-ui.ai-button>
                                <x-ui.ai-button target="aiKeywordIdeas" wire:click="aiKeywordIdeas('{{ $pk }}')">Keyword ideas</x-ui.ai-button>
                            </div>
                            @if (! empty($keywordIdeas[$pk]))
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($keywordIdeas[$pk] as $idea)
                                        <button type="button" wire:click="addKeyword('{{ $pk }}', @js($idea['keyword']))" class="rounded-full border border-forest-200 bg-white px-3 py-1 text-xs text-forest-800 hover:bg-forest-100" title="Search intent: {{ $idea['intent'] }}">+ {{ $idea['keyword'] }}</button>
                                    @endforeach
                                </div>
                            @endif
                            <p class="mt-2 text-xs text-zinc-500">Suggestions fill the fields below as a draft. Check facts and wording, then click Save.</p>
                        </div>
                    @endif

                    <div class="mt-5 space-y-5">
                        <div>
                            <flux:input wire:model.live.debounce.400ms="pages.{{ $pk }}.title" label="Page title" description="Shown as the blue link in search results. The brand is added automatically by the title template." />
                            <p class="mt-1 text-xs {{ $lenClass($tLen, 30, 60) }}">{{ $tLen }} characters including brand (ideal 30–60)</p>
                            <flux:error name="pages.{{ $pk }}.title" />
                        </div>
                        <div>
                            <flux:textarea wire:model.live.debounce.400ms="pages.{{ $pk }}.description" label="Meta description" rows="3" description="The summary under the title. Write for people: what they get and why click." />
                            <p class="mt-1 text-xs {{ $lenClass($dLen, 70, 160) }}">{{ $dLen }} characters (ideal 70–160)</p>
                            <flux:error name="pages.{{ $pk }}.description" />
                        </div>
                    </div>
                </x-ui.panel>

                <x-ui.panel title="Social sharing (Open Graph / X)" description="How the page looks when shared on WhatsApp, Facebook, LinkedIn and X." padded>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="max-w-sm overflow-hidden rounded-lg border border-zinc-200 bg-white">
                            <div class="aspect-[1200/630] bg-zinc-100">@if ($ogImg)<img src="{{ $ogImg }}" alt="" class="size-full object-cover">@endif</div>
                            <div class="bg-zinc-50 p-3">
                                <p class="text-[11px] uppercase text-zinc-500">{{ parse_url($baseUrl, PHP_URL_HOST) }}</p>
                                <p class="truncate text-sm font-semibold text-zinc-900">{{ $pv['og_title'] !== '' ? $pv['og_title'] : $pv['title'] }}</p>
                                <p class="line-clamp-2 text-xs text-zinc-600">{{ $pv['og_description'] !== '' ? $pv['og_description'] : $pv['description'] }}</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <flux:input wire:model.live.debounce.400ms="pages.{{ $pk }}.og_title" label="Social title (optional)" description="Leave blank to use the page title." />
                            <flux:textarea wire:model.live.debounce.400ms="pages.{{ $pk }}.og_description" label="Social description (optional)" rows="2" />
                            @include('livewire.pages.seo-field', ['model' => 'pages.'.$pk.'.og_image', 'field' => ['type' => 'image', 'label' => 'Sharing image', 'help' => '1200×630 px recommended. A default branded image is bundled.'], 'fileKey' => 'p__'.$pk.'__og_image', 'reset' => "resetImage('p', '{$pk}')"])
                        </div>
                    </div>
                </x-ui.panel>

                <x-ui.panel padded>
                    <flux:field variant="inline">
                        <flux:switch wire:model="pages.{{ $pk }}.noindex" />
                        <flux:label>Hide this page from search engines (noindex)</flux:label>
                        <flux:description>Only use this for pages you do not want to appear in search results. The page is also removed from the sitemap.</flux:description>
                    </flux:field>
                </x-ui.panel>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="savePage,files">Save {{ strtolower($pdef['label']) }} SEO</flux:button>
            </form>
        </div>
    @endif

    {{-- ================= CONTENT ================= --}}
    @if ($tab === 'content')
        <div class="grid gap-6 lg:grid-cols-[14rem_minmax(0,1fr)]">
            <nav aria-label="Content groups" class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs lg:self-start">
                @foreach (config('site.content') as $key => $def)
                    <button type="button" wire:click="$set('contentGroup', '{{ $key }}')" @class(['block w-full border-b border-zinc-100 px-4 py-3 text-left text-sm font-medium last:border-0', 'bg-forest-50 text-forest-800' => $contentGroup === $key, 'text-zinc-700 hover:bg-zinc-50' => $contentGroup !== $key])>{{ $def['label'] }}</button>
                @endforeach
            </nav>

            @php $group = config('site.content.'.$contentGroup); @endphp
            <form wire:submit="saveContent('{{ $contentGroup }}')" class="space-y-6" wire:key="content-{{ $contentGroup }}">
                <x-ui.panel :title="$group['label']" description="Change the words and images visitors see. Blank values restore the default text." padded>
                    <div class="space-y-5">
                        @foreach ($group['fields'] as $key => $field)
                            <div wire:key="c-{{ $contentGroup }}-{{ $key }}">
                                @include('livewire.pages.seo-field', ['model' => 'content.'.$contentGroup.'.'.$key, 'field' => $field, 'fileKey' => 'c__'.$contentGroup.'__'.$key, 'reset' => "resetImage('c', '{$contentGroup}', '{$key}')", 'ai' => ($aiReady && in_array($field['type'], ['text', 'textarea', 'list', 'pairs']) ? "aiContent('{$contentGroup}', '{$key}')" : null), 'aiTarget' => 'aiContent'])
                                <flux:error name="content.{{ $contentGroup }}.{{ $key }}" />
                            </div>
                        @endforeach
                    </div>
                </x-ui.panel>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveContent,files">Save {{ strtolower($group['label']) }}</flux:button>
            </form>
        </div>
    @endif

    {{-- ================= FAQS ================= --}}
    @if ($tab === 'faqs')
        <x-ui.panel title="Frequently asked questions" description="Shown on the FAQ page (with FAQ structured data) and, if ticked, on the home page.">
            <x-slot:actions>
                @if ($aiReady)<x-ui.ai-button target="aiFaqs" wire:click="aiFaqs">Generate FAQs with AI</x-ui.ai-button>@endif
                <flux:button size="sm" variant="primary" icon="plus" wire:click="editFaq">Add FAQ</flux:button>
            </x-slot:actions>
            @forelse ($faqs as $faq)
                <div class="flex items-start gap-3 border-b border-zinc-100 px-5 py-4 last:border-0" wire:key="faq-{{ $faq->id }}">
                    <div class="flex flex-col">
                        <button type="button" wire:click="moveFaq({{ $faq->id }}, -1)" class="text-zinc-400 hover:text-zinc-800" aria-label="Move up" @disabled($loop->first)><flux:icon.chevron-up variant="micro" /></button>
                        <button type="button" wire:click="moveFaq({{ $faq->id }}, 1)" class="text-zinc-400 hover:text-zinc-800" aria-label="Move down" @disabled($loop->last)><flux:icon.chevron-down variant="micro" /></button>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-zinc-900">{{ $faq->question }}</p>
                        <p class="mt-0.5 line-clamp-2 text-sm text-zinc-600">{{ $faq->answer }}</p>
                        <p class="mt-1 flex gap-2">
                            <flux:badge size="sm" :color="$faq->is_published ? 'green' : 'zinc'" inset="top bottom">{{ $faq->is_published ? 'Published' : 'Draft' }}</flux:badge>
                            @if ($faq->show_on_home)<flux:badge size="sm" color="sky" inset="top bottom">On home page</flux:badge>@endif
                        </p>
                    </div>
                    <div class="flex gap-1">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editFaq({{ $faq->id }})" aria-label="Edit" />
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteFaq({{ $faq->id }})" wire:confirm="Delete this FAQ?" aria-label="Delete" />
                    </div>
                </div>
            @empty
                <x-ui.empty icon="question-mark-circle" title="No FAQs yet" text="Add questions buyers and farmers ask most often." />
            @endforelse
        </x-ui.panel>

        <flux:modal wire:model.self="showFaqAi" class="w-full max-w-2xl">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">AI-suggested FAQs</flux:heading>
                    <flux:subheading>Tick the ones you want. They are added as <strong>drafts</strong> — read each answer for accuracy before publishing.</flux:subheading>
                </div>
                <div class="flex items-end gap-2">
                    <div class="flex-1"><flux:input wire:model="faqTopic" label="Focus on (optional)" placeholder="e.g. delivery, payment, farmer registration" /></div>
                    <x-ui.ai-button target="aiFaqs" wire:click="aiFaqs">Regenerate</x-ui.ai-button>
                </div>
                <div class="max-h-[50vh] space-y-3 overflow-y-auto">
                    @forelse ($faqCandidates as $i => $cand)
                        <label class="block cursor-pointer rounded-lg border border-zinc-200 p-3 hover:border-forest-300" wire:key="cand-{{ $i }}">
                            <span class="flex items-start gap-3">
                                <input type="checkbox" wire:model="faqCandidates.{{ $i }}.selected" class="mt-1 size-4 rounded border-zinc-300 text-forest-700">
                                <span><span class="block font-medium text-zinc-900">{{ $cand['question'] }}</span><span class="mt-1 block text-sm text-zinc-600">{{ $cand['answer'] }}</span></span>
                            </span>
                        </label>
                    @empty
                        <p class="text-sm text-zinc-500">No suggestions yet.</p>
                    @endforelse
                </div>
                <div class="flex justify-end gap-2"><flux:button type="button" variant="ghost" wire:click="$set('showFaqAi', false)">Cancel</flux:button><flux:button type="button" variant="primary" wire:click="addFaqCandidates">Add selected as drafts</flux:button></div>
            </div>
        </flux:modal>

        <flux:modal wire:model.self="showFaq" class="w-full max-w-xl">
            <form wire:submit="saveFaq" class="space-y-4">
                <flux:heading size="lg">{{ $faqId ? 'Edit FAQ' : 'New FAQ' }}</flux:heading>
                <flux:input wire:model="faqForm.question" label="Question *" />
                <flux:textarea wire:model="faqForm.answer" label="Answer *" rows="5" description="Plain text. Line breaks are kept." />
                <flux:input type="number" wire:model="faqForm.sort_order" label="Sort order" description="Lower numbers appear first." />
                <flux:field variant="inline"><flux:switch wire:model="faqForm.is_published" /><flux:label>Published</flux:label></flux:field>
                <flux:field variant="inline"><flux:switch wire:model="faqForm.show_on_home" /><flux:label>Show on the home page</flux:label></flux:field>
                <div class="flex justify-end gap-2"><flux:button type="button" variant="ghost" wire:click="$set('showFaq', false)">Cancel</flux:button><flux:button type="submit" variant="primary">Save FAQ</flux:button></div>
            </form>
        </flux:modal>
    @endif

    {{-- ================= CATALOGUE ================= --}}
    @if ($tab === 'catalogue')
        <x-ui.panel title="Category pages" description="Each category has its own page at /produce/category/<url>. Add an introduction paragraph — it is the page's main content for search engines.">
            @foreach ($categories as $c)
                <div class="space-y-3 border-b border-zinc-100 p-5 last:border-0" wire:key="cat-{{ $c->id }}">
                    <div class="flex flex-wrap items-center justify-between gap-2"><p class="font-medium text-zinc-900">{{ $c->name }}</p><a class="text-xs text-forest-700 underline" target="_blank" href="{{ route('site.category', $c->slug) }}">View page</a></div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div><flux:input wire:model="categorySeo.{{ $c->id }}.slug" label="URL slug" /><flux:error name="categorySeo.{{ $c->id }}.slug" /></div>
                        <div class="sm:col-span-2"><flux:input wire:model="categorySeo.{{ $c->id }}.meta_title" label="Meta title (optional)" /></div>
                    </div>
                    <flux:input wire:model="categorySeo.{{ $c->id }}.meta_description" label="Meta description (optional)" />
                    <flux:textarea wire:model="categorySeo.{{ $c->id }}.seo_text" label="Introduction text" rows="2" />
                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" wire:click="saveCategory({{ $c->id }})">Save category</flux:button>
                        @if ($aiReady)<x-ui.ai-button target="aiCategory({{ $c->id }})" wire:click="aiCategory({{ $c->id }})">Suggest with AI</x-ui.ai-button>@endif
                    </div>
                </div>
            @endforeach
        </x-ui.panel>

        <x-ui.panel title="Product pages" description="Each product has its own page at /produce/<url> with a title, description, Open Graph image and Product structured data.">
            <div class="flex flex-wrap items-end gap-3 border-b border-zinc-200 p-4">
                <div class="w-full sm:w-64"><flux:input wire:model.live.debounce.300ms="catSearch" icon="magnifying-glass" placeholder="Search products…" clearable /></div>
                <flux:checkbox wire:model.live="missingOnly" label="Only products without a meta description" />
                @if ($aiReady)
                    <x-ui.ai-button target="aiProductsMissing" wire:click="aiProductsMissing(false)">AI: fill missing SEO on this page</x-ui.ai-button>
                    <x-ui.ai-button target="aiProductsMissing" wire:click="aiProductsMissing(true)">…and write descriptions</x-ui.ai-button>
                @endif
                <flux:button size="sm" wire:click="saveAllProducts" wire:loading.attr="disabled" wire:target="saveAllProducts">Save all on this page</flux:button>
            </div>
            @forelse ($products as $p)
                @php
                    $row = $catalogue[$p->id] ?? ['slug' => $p->slug, 'meta_title' => '', 'meta_description' => ''];
                    $mdLen = Str::length((string) $row['meta_description']);
                @endphp
                <div class="space-y-3 border-b border-zinc-100 p-5 last:border-0" wire:key="prod-{{ $p->id }}">
                    <div class="flex items-center gap-3">
                        <div class="size-10 shrink-0 overflow-hidden rounded-lg bg-forest-50">@if ($p->imageUrl())<img src="{{ $p->imageUrl() }}" alt="" class="size-full object-cover">@endif</div>
                        <div class="min-w-0 flex-1"><p class="truncate font-medium text-zinc-900">{{ $p->name }}</p><p class="text-xs text-zinc-500">{{ $p->category?->name }} · {{ Str::length((string) $p->description) }} chars of description</p></div>
                        <a class="text-xs text-forest-700 underline" target="_blank" href="{{ route('site.product', $p->slug) }}">View page</a>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div><flux:input wire:model="catalogue.{{ $p->id }}.slug" label="URL slug" /><flux:error name="catalogue.{{ $p->id }}.slug" /></div>
                        <div class="sm:col-span-2"><flux:input wire:model="catalogue.{{ $p->id }}.meta_title" label="Meta title (optional)" placeholder="{{ $p->name }} – Wholesale {{ $p->category?->name }} Kenya" /></div>
                    </div>
                    <div>
                        <flux:textarea wire:model.live.debounce.400ms="catalogue.{{ $p->id }}.meta_description" label="Meta description (optional)" rows="2" placeholder="Leave blank to generate one from the product description." />
                        <p class="mt-1 text-xs {{ $lenClass($mdLen, 70, 160) }}">{{ $mdLen }} characters (ideal 70–160)</p>
                        <flux:error name="catalogue.{{ $p->id }}.meta_description" />
                    </div>
                    <div>
                        <flux:textarea wire:model="catalogue.{{ $p->id }}.description" label="Product description (shown on the product page)" rows="2" />
                        <flux:error name="catalogue.{{ $p->id }}.description" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" wire:click="saveProduct({{ $p->id }})">Save product SEO</flux:button>
                        @if ($aiReady)
                            <x-ui.ai-button target="aiProduct({{ $p->id }}, false)" wire:click="aiProduct({{ $p->id }}, false)">Suggest SEO</x-ui.ai-button>
                            <x-ui.ai-button target="aiProduct({{ $p->id }}, true)" wire:click="aiProduct({{ $p->id }}, true)">Suggest SEO + description</x-ui.ai-button>
                        @endif
                    </div>
                </div>
            @empty
                <x-ui.empty icon="cube" title="No products found" />
            @endforelse
            <div class="px-4 pb-3">{{ $products->links() }}</div>
        </x-ui.panel>
        <p class="text-xs text-zinc-500">Product descriptions and photos are edited on the Products page. Changing a slug changes the product's public URL — only do it for new products.</p>
    @endif
</div>
