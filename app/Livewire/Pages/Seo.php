<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Services\Ai\GeminiException;
use App\Services\Ai\SeoAi;
use App\Services\AuditLogger;
use App\Services\SeoAudit;
use App\Support\Seo as SeoSupport;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Administration → SEO & Site Content. Manages global SEO, per-page metadata, editable website copy,
 * FAQs and catalogue (product/category) SEO, with a live health check.
 */
class Seo extends Component
{
    use Page, WithFileUploads, WithPagination;

    public const TABS = ['overview' => 'Overview', 'global' => 'Global SEO', 'pages' => 'Pages', 'content' => 'Site content', 'faqs' => 'FAQs', 'catalogue' => 'Products & categories'];

    #[Url]
    public string $tab = 'overview';

    public array $global = [];

    public array $pages = [];

    public array $content = [];

    /** Temporary uploads keyed "g__field", "p__page__field", "c__group__field". */
    public array $files = [];

    public string $pageKey = 'home';

    public string $contentGroup = 'site';

    // FAQs
    public bool $showFaq = false;

    public ?int $faqId = null;

    public array $faqForm = [];

    // Catalogue
    public string $catSearch = '';

    public bool $missingOnly = false;

    public array $catalogue = [];

    public array $categorySeo = [];

    // AI assistant (Gemini). Suggestions are drafted into the forms; nothing is saved until an admin saves.
    /** @var array<string, string> keyword hints keyed by page key */
    public array $aiKeywords = [];

    /** @var array<string, list<array{keyword:string, intent:string}>> */
    public array $keywordIdeas = [];

    /** @var list<array{question:string, answer:string, selected:bool}> */
    public array $faqCandidates = [];

    public bool $showFaqAi = false;

    public string $faqTopic = '';

    public ?array $aiStatus = null;

    public function mount(): void
    {
        $this->guard('seo.manage');

        if (! isset(self::TABS[$this->tab])) {
            $this->tab = 'overview';
        }

        $this->loadForms();
    }

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    public function updatedCatSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMissingOnly(): void
    {
        $this->resetPage();
    }

    // ---- Loading -------------------------------------------------------------------------------------

    protected function loadForms(): void
    {
        foreach (config('site.global') as $key => $def) {
            $value = SeoSupport::setting($key);
            $this->global[$key] = $def['type'] === 'switch' ? in_array($value, ['1', 'true', 'on'], true) : $value;
        }

        foreach (config('site.pages') as $page => $def) {
            $c = SeoSupport::pageConfig($page);
            $this->pages[$page] = [
                'title' => $c['title'], 'description' => $c['description'], 'og_title' => (string) $c['og_title'],
                'og_description' => (string) $c['og_description'], 'og_image' => (string) $c['og_image'], 'noindex' => $c['noindex'],
            ];
        }

        foreach (config('site.content') as $group => $def) {
            foreach ($def['fields'] as $key => $field) {
                $value = \App\Support\SiteContent::get("{$group}.{$key}");
                $this->content[$group][$key] = $field['type'] === 'switch' ? in_array($value, ['1', 'true', 'on'], true) : $value;
            }
        }
    }

    // ---- Saving helpers ------------------------------------------------------------------------------

    /** Save a value, or drop the override when it equals the default (so future default changes still apply). */
    protected function store(string $key, ?string $value, ?string $default): void
    {
        $value = trim((string) $value);

        if ($value === trim((string) $default) || ($value === '' && trim((string) $default) === '')) {
            Setting::forget($key);

            return;
        }

        Setting::put($key, $value);
    }

    /** Validate and store an uploaded image; returns the stored path or null when nothing was uploaded. */
    protected function storeUpload(string $fileKey, string $label): ?string
    {
        $file = $this->files[$fileKey] ?? null;

        if (! $file) {
            return null;
        }

        $this->validate(["files.{$fileKey}" => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096']], [], ["files.{$fileKey}" => strtolower($label)]);

        $path = $file->store('seo', 'public');
        unset($this->files[$fileKey]);

        return $path;
    }

    protected function finish(string $what): void
    {
        Setting::flushCache();
        AuditLogger::log('seo_changed', null, $what.' updated in SEO & Site Content');
        $this->toast($what.' saved.');
    }

    // ---- Global SEO ----------------------------------------------------------------------------------

    public function saveGlobal(): void
    {
        $this->guard('seo.manage');

        $rules = [];

        foreach (config('site.global') as $key => $def) {
            $rules["global.{$key}"] = match ($def['type']) {
                'url' => ['nullable', 'url', 'max:255'],
                'textarea' => ['nullable', 'string', 'max:2000'],
                'switch' => ['boolean'],
                'image' => ['nullable', 'string', 'max:255'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        $rules['global.ga4_id'] = ['nullable', 'regex:/^G-[A-Z0-9]{6,14}$/'];
        $rules['global.twitter_handle'] = ['nullable', 'regex:/^@?[A-Za-z0-9_]{1,15}$/'];
        $rules['global.title_template'] = ['required', 'string', 'max:120', 'regex:/%s/'];
        $rules['global.site_name'] = ['required', 'string', 'max:80'];
        $rules['global.locale'] = ['required', 'regex:/^[a-z]{2}[_-][A-Z]{2}$/'];
        $rules['global.org_founding_year'] = ['nullable', 'digits:4'];

        $this->validate($rules, ['global.title_template.regex' => 'The title template must contain %s where the page title goes.', 'global.ga4_id.regex' => 'Enter an ID like G-XXXXXXXXXX.'], ['global.ga4_id' => 'Analytics ID']);

        foreach (config('site.global') as $key => $def) {
            if ($def['type'] === 'image') {
                $path = $this->storeUpload("g__{$key}", $def['label']);

                if ($path) {
                    $this->global[$key] = $path;
                }
            }

            $value = match (true) {
                $def['type'] === 'switch' => $this->global[$key] ? '1' : '0',
                $key === 'twitter_handle' => ltrim((string) $this->global[$key], '@'),
                default => (string) $this->global[$key],
            };

            // A switched-off value ('0') must be stored even though it is falsy-looking.
            if ($def['type'] === 'switch') {
                $value === (string) ($def['default'] ?? '') ? Setting::forget("seo.{$key}") : Setting::put("seo.{$key}", $value);

                continue;
            }

            $this->store("seo.{$key}", $value, $def['default'] ?? '');
        }

        $this->finish('Global SEO');
    }

    public function resetImage(string $scope, string $a, ?string $b = null): void
    {
        $this->guard('seo.manage');

        if ($scope === 'g') {
            Setting::forget("seo.{$a}");
            $this->global[$a] = config("site.global.{$a}.default");
        } elseif ($scope === 'p') {
            Setting::forget("seo.page.{$a}.og_image");
            $this->pages[$a]['og_image'] = (string) config("site.pages.{$a}.og");
        } else {
            Setting::forget("content.{$a}.{$b}");
            $this->content[$a][$b] = config("site.content.{$a}.fields.{$b}.default");
        }

        Setting::flushCache();
        $this->toast('Image reset to the default.');
    }

    // ---- Pages ---------------------------------------------------------------------------------------

    public function savePage(string $page): void
    {
        $this->guard('seo.manage');
        abort_unless(isset(config('site.pages')[$page]), 404);

        $this->validate([
            "pages.{$page}.title" => ['required', 'string', 'min:10', 'max:120'],
            "pages.{$page}.description" => ['required', 'string', 'min:30', 'max:320'],
            "pages.{$page}.og_title" => ['nullable', 'string', 'max:120'],
            "pages.{$page}.og_description" => ['nullable', 'string', 'max:300'],
        ], [], ["pages.{$page}.title" => 'title', "pages.{$page}.description" => 'meta description']);

        $def = config("site.pages.{$page}");
        $p = $this->pages[$page];

        $path = $this->storeUpload("p__{$page}__og_image", 'sharing image');

        if ($path) {
            $this->pages[$page]['og_image'] = $path;
            $p['og_image'] = $path;
        }

        $this->store("seo.page.{$page}.title", $p['title'], $def['title']);
        $this->store("seo.page.{$page}.description", $p['description'], $def['description']);
        $this->store("seo.page.{$page}.og_title", $p['og_title'], '');
        $this->store("seo.page.{$page}.og_description", $p['og_description'], '');
        $this->store("seo.page.{$page}.og_image", $p['og_image'], $def['og']);
        $p['noindex'] ? Setting::put("seo.page.{$page}.noindex", '1') : Setting::forget("seo.page.{$page}.noindex");

        $this->finish($def['label'].' page SEO');
    }

    // ---- Content -------------------------------------------------------------------------------------

    public function saveContent(string $group): void
    {
        $this->guard('seo.manage');
        abort_unless(isset(config('site.content')[$group]), 404);

        $fields = config("site.content.{$group}.fields");
        $rules = [];

        foreach ($fields as $key => $field) {
            $rules["content.{$group}.{$key}"] = match ($field['type']) {
                'url' => ['nullable', 'url', 'max:255'],
                'switch' => ['boolean'],
                'textarea', 'list', 'pairs' => ['nullable', 'string', 'max:4000'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        $this->validate($rules);

        foreach ($fields as $key => $field) {
            if ($field['type'] === 'image') {
                $path = $this->storeUpload("c__{$group}__{$key}", $field['label']);

                if ($path) {
                    $this->content[$group][$key] = $path;
                }
            }

            $value = $field['type'] === 'switch' ? ($this->content[$group][$key] ? '1' : '0') : (string) $this->content[$group][$key];
            $this->store("content.{$group}.{$key}", $value, $field['default'] ?? '');
        }

        $this->finish(config("site.content.{$group}.label").' content');
    }

    // ---- FAQs ----------------------------------------------------------------------------------------

    public function editFaq(?int $id = null): void
    {
        $this->guard('seo.manage');
        $this->resetValidation();
        $faq = $id ? Faq::findOrFail($id) : null;
        $this->faqId = $faq?->id;
        $this->faqForm = [
            'question' => $faq?->question ?? '', 'answer' => $faq?->answer ?? '', 'sort_order' => $faq?->sort_order ?? ((int) Faq::max('sort_order') + 10),
            'is_published' => $faq?->is_published ?? true, 'show_on_home' => $faq?->show_on_home ?? false,
        ];
        $this->showFaq = true;
    }

    public function saveFaq(): void
    {
        $this->guard('seo.manage');
        $this->validate([
            'faqForm.question' => ['required', 'string', 'min:8', 'max:255'],
            'faqForm.answer' => ['required', 'string', 'min:10', 'max:3000'],
            'faqForm.sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'faqForm.is_published' => ['boolean'],
            'faqForm.show_on_home' => ['boolean'],
        ], [], ['faqForm.question' => 'question', 'faqForm.answer' => 'answer']);

        $this->faqId ? Faq::findOrFail($this->faqId)->update($this->faqForm) : Faq::create($this->faqForm);
        $this->showFaq = false;
        $this->toast('FAQ saved.');
    }

    public function deleteFaq(int $id): void
    {
        $this->guard('seo.manage');
        Faq::findOrFail($id)->delete();
        $this->toast('FAQ deleted.');
    }

    public function moveFaq(int $id, int $direction): void
    {
        $this->guard('seo.manage');
        $faqs = Faq::orderBy('sort_order')->orderBy('id')->get()->values();
        $i = $faqs->search(fn ($f) => $f->id === $id);
        $j = $i + $direction;

        if ($i === false || $j < 0 || $j >= $faqs->count()) {
            return;
        }

        // Re-number so the swap is stable even when sort orders were equal.
        $order = $faqs->pluck('id')->all();
        [$order[$i], $order[$j]] = [$order[$j], $order[$i]];

        foreach ($order as $position => $faqId) {
            Faq::whereKey($faqId)->update(['sort_order' => ($position + 1) * 10]);
        }
    }

    // ---- Catalogue -----------------------------------------------------------------------------------

    public function saveProduct(int $id): void
    {
        $this->guard('seo.manage');
        $row = $this->catalogue[$id] ?? null;
        abort_unless($row, 404);

        $this->validate([
            "catalogue.{$id}.slug" => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:products,slug,'.$id],
            "catalogue.{$id}.meta_title" => ['nullable', 'string', 'max:120'],
            "catalogue.{$id}.meta_description" => ['nullable', 'string', 'max:320'],
            "catalogue.{$id}.description" => ['nullable', 'string', 'max:2000'],
        ], ['catalogue.*.slug.regex' => 'Use lowercase letters, numbers and hyphens only.', 'catalogue.*.slug.unique' => 'That URL is already used by another product.']);

        $update = ['slug' => $row['slug'], 'meta_title' => $row['meta_title'] ?: null, 'meta_description' => $row['meta_description'] ?: null];

        // The description is only touched when the row carries one (it is always loaded by the UI).
        if (array_key_exists('description', $row)) {
            $update['description'] = $row['description'] ?: null;
        }

        Product::findOrFail($id)->update($update);
        $this->toast('Product SEO saved.');
    }

    /** Save every product row shown on the current page (used after AI drafting). */
    public function saveAllProducts(): void
    {
        $this->guard('seo.manage');
        $ids = Product::orderBy('name')
            ->when($this->catSearch !== '', fn ($q) => $q->where('name', 'like', '%'.$this->catSearch.'%'))
            ->when($this->missingOnly, fn ($q) => $q->where(fn ($w) => $w->whereNull('meta_description')->orWhere('meta_description', '')))
            ->paginate(12)->pluck('id')->all();

        $rules = [];

        foreach ($ids as $id) {
            $rules["catalogue.{$id}.slug"] = ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:products,slug,'.$id];
            $rules["catalogue.{$id}.meta_title"] = ['nullable', 'string', 'max:120'];
            $rules["catalogue.{$id}.meta_description"] = ['nullable', 'string', 'max:320'];
            $rules["catalogue.{$id}.description"] = ['nullable', 'string', 'max:2000'];
        }

        $this->validate($rules);

        foreach ($ids as $id) {
            $row = $this->catalogue[$id];
            Product::findOrFail($id)->update(['slug' => $row['slug'], 'meta_title' => $row['meta_title'] ?: null, 'meta_description' => $row['meta_description'] ?: null, 'description' => $row['description'] ?: null]);
        }

        AuditLogger::log('seo_changed', null, count($ids).' products updated in SEO & Site Content');
        $this->toast(count($ids).' products saved.');
    }

    public function saveCategory(int $id): void
    {
        $this->guard('seo.manage');
        $row = $this->categorySeo[$id] ?? null;
        abort_unless($row, 404);

        $this->validate([
            "categorySeo.{$id}.slug" => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:product_categories,slug,'.$id],
            "categorySeo.{$id}.meta_title" => ['nullable', 'string', 'max:120'],
            "categorySeo.{$id}.meta_description" => ['nullable', 'string', 'max:320'],
            "categorySeo.{$id}.seo_text" => ['nullable', 'string', 'max:2000'],
        ]);

        ProductCategory::findOrFail($id)->update([
            'slug' => $row['slug'], 'meta_title' => $row['meta_title'] ?: null, 'meta_description' => $row['meta_description'] ?: null, 'seo_text' => $row['seo_text'] ?: null,
        ]);
        $this->toast('Category SEO saved.');
    }

    // ---- AI assistant --------------------------------------------------------------------------------

    /**
     * Run an AI action with permission, quota and error handling. Returns null (with a toast) on failure.
     *
     * @template T
     *
     * @param  callable(SeoAi): T  $fn
     * @return T|null
     */
    protected function ai(callable $fn, string $what): mixed
    {
        $this->guard('seo.manage');
        $ai = app(SeoAi::class);

        if (! $ai->configured()) {
            $this->toast('The AI assistant is not configured. Add GEMINI_API_KEY to the .env file.', 'danger');

            return null;
        }

        try {
            @set_time_limit(120);
            $ai->throttle(auth()->id());
            $result = $fn($ai);
            AuditLogger::log('ai_generated', null, 'AI suggestion generated: '.$what);

            return $result;
        } catch (GeminiException $e) {
            $this->toast($e->getMessage(), 'danger');

            return null;
        }
    }

    protected function drafted(string $what = 'Suggestion'): void
    {
        $this->toast($what.' filled in. Review and edit it, then click Save.');
    }

    public function aiTest(): void
    {
        $this->aiStatus = null;
        $result = $this->ai(fn (SeoAi $ai) => $ai->test(), 'connection test');
        $this->aiStatus = $result ? ['ok' => true] + $result : ['ok' => false];
        $result && $this->toast('Connected to Gemini ('.$result['model'].').');
    }

    public function aiPage(string $page): void
    {
        abort_unless(isset(config('site.pages')[$page]), 404);
        $r = $this->ai(fn (SeoAi $ai) => $ai->page($page, $this->aiKeywords[$page] ?? ''), config("site.pages.{$page}.label").' page SEO');

        if ($r) {
            $this->pages[$page] = array_merge($this->pages[$page], $r);
            $this->drafted();
        }
    }

    public function aiKeywordIdeas(string $page): void
    {
        abort_unless(isset(config('site.pages')[$page]), 404);
        $r = $this->ai(fn (SeoAi $ai) => $ai->keywords($page), config("site.pages.{$page}.label").' keyword ideas');

        if ($r !== null) {
            $this->keywordIdeas[$page] = $r;
        }
    }

    public function addKeyword(string $page, string $keyword): void
    {
        $this->guard('seo.manage');
        $current = array_filter(array_map('trim', explode(',', $this->aiKeywords[$page] ?? '')));
        $current[] = mb_substr(strip_tags($keyword), 0, 60);
        $this->aiKeywords[$page] = implode(', ', array_unique($current));
    }

    public function aiGlobal(): void
    {
        $r = $this->ai(fn (SeoAi $ai) => $ai->globalDescription($this->aiKeywords['global'] ?? ''), 'default meta description');

        if ($r) {
            $this->global['default_description'] = $r;
            $this->drafted();
        }
    }

    public function aiContent(string $group, string $key): void
    {
        $field = config("site.content.{$group}.fields.{$key}");
        abort_unless($field && in_array($field['type'], ['text', 'textarea', 'list', 'pairs'], true), 404);

        $r = $this->ai(fn (SeoAi $ai) => $ai->improve($field['label'], (string) $this->content[$group][$key], $field['type']), $field['label']);

        if ($r) {
            $this->content[$group][$key] = $r;
            $this->drafted();
        }
    }

    /** Fill one product row with AI SEO copy (optionally also the product description). */
    public function aiProduct(int $id, bool $withDescription = false): void
    {
        $product = Product::with('category')->findOrFail($id);
        $r = $this->ai(fn (SeoAi $ai) => $ai->products(collect([$product]), $withDescription), $product->name.' product SEO');

        if ($r && isset($r[$id])) {
            $this->applyProductSuggestion($id, $r[$id]);
            $this->drafted();
        }
    }

    protected function applyProductSuggestion(int $id, array $s): void
    {
        $row = $this->catalogue[$id] ?? ['slug' => Product::whereKey($id)->value('slug'), 'meta_title' => '', 'meta_description' => '', 'description' => ''];
        $row['meta_title'] = $s['meta_title'];
        $row['meta_description'] = $s['meta_description'];

        if (! empty($s['description'])) {
            $row['description'] = $s['description'];
        }

        $this->catalogue[$id] = $row;
    }

    /** One AI request for the products on this page that still lack a meta description (max 10). */
    public function aiProductsMissing(bool $withDescription = false): void
    {
        $products = Product::with('category')->orderBy('name')
            ->when($this->catSearch !== '', fn ($q) => $q->where('name', 'like', '%'.$this->catSearch.'%'))
            ->paginate(12)->getCollection()
            ->filter(fn ($p) => blank($this->catalogue[$p->id]['meta_description'] ?? $p->meta_description))->take(10);

        if ($products->isEmpty()) {
            $this->toast('Every product on this page already has a meta description.');

            return;
        }

        $r = $this->ai(fn (SeoAi $ai) => $ai->products($products, $withDescription), $products->count().' products SEO');

        if ($r) {
            foreach ($r as $id => $s) {
                $this->applyProductSuggestion($id, $s);
            }

            $this->drafted(count($r).' product suggestion(s)');
        }
    }

    public function aiCategory(int $id): void
    {
        $category = ProductCategory::findOrFail($id);
        $r = $this->ai(fn (SeoAi $ai) => $ai->category($category), $category->name.' category SEO');

        if ($r) {
            $this->categorySeo[$id] = array_merge($this->categorySeo[$id] ?? ['slug' => $category->slug], $r);
            $this->drafted();
        }
    }

    public function aiFaqs(): void
    {
        $r = $this->ai(fn (SeoAi $ai) => $ai->faqs(6, $this->faqTopic), 'FAQ ideas');

        if ($r !== null) {
            $this->faqCandidates = array_map(fn ($f) => $f + ['selected' => true], $r);
            $this->showFaqAi = true;
        }
    }

    /** Add the ticked suggestions as DRAFT FAQs so nothing goes live unreviewed. */
    public function addFaqCandidates(): void
    {
        $this->guard('seo.manage');
        $order = (int) Faq::max('sort_order');
        $added = 0;

        foreach ($this->faqCandidates as $c) {
            if (empty($c['selected'])) {
                continue;
            }

            Faq::create(['question' => $c['question'], 'answer' => $c['answer'], 'sort_order' => $order += 10, 'is_published' => false, 'show_on_home' => false]);
            $added++;
        }

        $this->showFaqAi = false;
        $this->faqCandidates = [];
        $this->toast($added ? $added.' FAQ(s) added as drafts. Review them below, then publish.' : 'No FAQs were selected.');
    }

    // ---- Render --------------------------------------------------------------------------------------

    public function render()
    {
        $data = ['tabs' => self::TABS];

        if ($this->tab === 'overview') {
            $data['audit'] = app(SeoAudit::class)->run();
        }

        if ($this->tab === 'faqs') {
            $data['faqs'] = Faq::orderBy('sort_order')->orderBy('id')->get();
        }

        if ($this->tab === 'catalogue') {
            $products = Product::with('category')->orderBy('name')
                ->when($this->catSearch !== '', fn ($q) => $q->where('name', 'like', '%'.$this->catSearch.'%'))
                ->when($this->missingOnly, fn ($q) => $q->where(fn ($w) => $w->whereNull('meta_description')->orWhere('meta_description', '')))
                ->paginate(12);

            foreach ($products as $p) {
                $this->catalogue[$p->id] ??= ['slug' => $p->slug, 'meta_title' => (string) $p->meta_title, 'meta_description' => (string) $p->meta_description, 'description' => (string) $p->description];
            }

            $categories = ProductCategory::orderBy('name')->get();

            foreach ($categories as $c) {
                $this->categorySeo[$c->id] ??= ['slug' => $c->slug, 'meta_title' => (string) $c->meta_title, 'meta_description' => (string) $c->meta_description, 'seo_text' => (string) $c->seo_text];
            }

            $data += ['products' => $products, 'categories' => $categories];
        }

        $data['aiReady'] = app(SeoAi::class)->configured();
        $data['aiModels'] = app(\App\Services\Ai\GeminiClient::class)->models();
        $data['siteTitleTemplate'] = SeoSupport::setting('title_template');
        $data['siteName'] = SeoSupport::siteName();
        $data['baseUrl'] = SeoSupport::baseUrl();

        return $this->page('livewire.pages.seo', $data, 'SEO & Site Content');
    }
}
