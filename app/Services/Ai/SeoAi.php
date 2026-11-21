<?php

namespace App\Services\Ai;

use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Support\Options;
use App\Support\Seo;
use App\Support\SiteContent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * AI-assisted SEO copy for Village City. Every method returns *suggestions* as plain arrays;
 * nothing is saved here — an administrator reviews and saves them in the admin UI.
 *
 * Privacy: prompts contain only public catalogue and website facts. Costs, customers, farmers,
 * orders and any financial data are never sent.
 */
class SeoAi
{
    /** Verified platform facts the model may rely on. Anything else must not be invented. */
    protected const FACTS = <<<'TXT'
Village City is a Kenyan agricultural aggregation, digital marketplace, wholesale distribution and logistics business.
- It buys fresh produce and cereals directly from farmers and cooperatives (mainly Western Kenya and Nyanza) at rural collection centres.
- Produce is inspected and graded (A, B, C) at the collection centre; only accepted produce is sold.
- Its promise is "from the farm to the city, within 24 hours".
- Customers: wholesale traders, supermarkets, restaurants, hotels, schools, hospitals, universities, government institutions, food processors and households.
- Ordering: browse the online marketplace, add to cart, choose delivery date and location; orders are confirmed, then dispatched and tracked.
- Payment: cash, bank transfer or M-Pesa; credit terms (net 7, 14 or 30 days) can be agreed for institutional customers; an invoice is issued when an order is confirmed.
- Each product has a minimum order quantity. Prices are indicative and confirmed at checkout.
- Farmers and cooperatives can register on the website; the team confirms details, links them to a collection centre and pays for the quantity accepted after grading.
- Customers can cancel an order while it is pending confirmation.
Currency is Kenyan shillings (KES). Spelling: British English.
TXT;

    public function __construct(protected GeminiClient $gemini) {}

    public function configured(): bool
    {
        return $this->gemini->configured();
    }

    public function model(): ?string
    {
        return $this->gemini->lastModel;
    }

    // ---- Guard ---------------------------------------------------------------------------------------

    /** Per-user hourly limit so a loop or a mis-click cannot burn the API quota. */
    public function throttle(?int $userId): void
    {
        $key = 'gemini:'.($userId ?? 'guest');
        $limit = max(5, (int) config('services.gemini.hourly_limit', 60));

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            throw new GeminiException('You have reached the hourly limit for AI suggestions ('.$limit.'). Please try again later.');
        }

        RateLimiter::hit($key, 3600);
    }

    // ---- Shared prompt pieces ------------------------------------------------------------------------

    protected function system(): string
    {
        return "You are a senior SEO copywriter for Village City, a Kenyan agricultural supplier. Write clear, specific, trustworthy copy for Kenyan business buyers and farmers.\n"
            ."Rules: use only the facts provided below and in the request; never invent prices, certifications, awards, statistics, locations, brands or guarantees; avoid hype, clichés and ALL CAPS; "
            ."write for people first, search engines second; put the main keyword near the start of titles; do not stuff keywords; British English.\n\n"
            ."VERIFIED FACTS\n".self::FACTS;
    }

    /** Characters available for a page title once the brand template is applied. */
    protected function titleRoom(): int
    {
        return max(25, 60 - (mb_strlen(Seo::title('X')) - 1));
    }

    protected function keywordsLine(string $keywords): string
    {
        $keywords = trim(strip_tags($keywords));

        return $keywords !== '' ? "Target keywords to work in naturally (only where they fit): {$keywords}\n" : '';
    }

    /** Clean model output and cut it to a maximum length at a word boundary. */
    public static function fit(?string $text, int $max): string
    {
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5);
        $text = trim(preg_replace('/\s+/u', ' ', $text), " \t\n\r\0\x0B\"'`");

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false && $space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, " ,;:-–—");
    }

    protected static function obj(array $props, array $required = []): array
    {
        return ['type' => 'OBJECT', 'properties' => $props, 'required' => $required ?: array_keys($props)];
    }

    protected const STR = ['type' => 'STRING'];

    // ---- Pages ---------------------------------------------------------------------------------------

    /**
     * Title, meta description and social copy for a public page.
     *
     * @return array{title:string, description:string, og_title:string, og_description:string}
     */
    public function page(string $page, string $keywords = ''): array
    {
        $def = config("site.pages.{$page}");
        abort_unless($def, 404);
        $current = Seo::pageConfig($page);
        $room = $this->titleRoom();

        $prompt = "Write SEO metadata for the \"{$def['label']}\" page ({$def['path']}) of the Village City website.\n"
            .$this->keywordsLine($keywords)
            ."Current title: {$current['title']}\nCurrent description: {$current['description']}\n\nPage context:\n".$this->pageContext($page)."\n"
            ."Requirements:\n- title: at most {$room} characters, WITHOUT the brand name (it is appended automatically).\n"
            ."- description: 120 to 155 characters, one or two sentences, ends with a reason to click.\n"
            ."- og_title: at most 70 characters, may include the brand, written for social sharing.\n"
            .'- og_description: at most 110 characters, friendly and concrete.';

        $r = $this->gemini->json($prompt, self::obj(['title' => self::STR, 'description' => self::STR, 'og_title' => self::STR, 'og_description' => self::STR]), $this->system(), 0.5);

        return [
            'title' => self::fit($r['title'] ?? '', $room),
            'description' => self::fit($r['description'] ?? '', 158),
            'og_title' => self::fit($r['og_title'] ?? '', 70),
            'og_description' => self::fit($r['og_description'] ?? '', 120),
        ];
    }

    protected function pageContext(string $page): string
    {
        $categories = ProductCategory::where('status', 'active')->pluck('name')->implode(', ');
        $products = Product::where('status', 'active')->orderBy('name')->limit(25)->pluck('name')->implode(', ');

        return match ($page) {
            'home' => 'Hero text: '.SiteContent::get('home.hero_text')."\nProduct ranges: {$categories}\nSample commodities: {$products}",
            'produce' => "Catalogue of wholesale produce, cereals and dry foods.\nRanges: {$categories}\nCommodities: {$products}",
            'about' => 'Mission: '.SiteContent::get('about.mission')."\nVision: ".SiteContent::get('about.vision'),
            'sell' => 'Farmer and cooperative registration. '.SiteContent::get('sell.hero_text'),
            'contact' => 'Enquiries about wholesale and institutional supply, farmer registration, partnerships and order support.',
            'faq' => 'Questions: '.Faq::published()->limit(12)->pluck('question')->implode(' | '),
            default => '',
        };
    }

    /** Default site-wide meta description. */
    public function globalDescription(string $keywords = ''): string
    {
        $prompt = 'Write the default meta description for the whole Village City website (used when a page has none). '
            .$this->keywordsLine($keywords)."Current: ".Seo::setting('default_description')."\n"
            .'Requirement: 120 to 155 characters, mentions what Village City does and who it serves.';

        $r = $this->gemini->json($prompt, self::obj(['description' => self::STR]), $this->system(), 0.5);

        return self::fit($r['description'] ?? '', 158);
    }

    // ---- Products and categories ---------------------------------------------------------------------

    protected function productFacts(Product $p): string
    {
        return json_encode(array_filter([
            'id' => $p->id,
            'name' => $p->name,
            'category' => $p->category?->name,
            'unit' => Options::label('UNITS', $p->unit),
            'type' => $p->is_perishable ? 'fresh / perishable' : 'dry / non-perishable',
            'shelf_life_days' => $p->shelf_life_days,
            'storage' => $p->storage_requirements,
            'minimum_order' => Options::qty($p->min_order_qty).' '.$p->unit,
            'current_description' => $p->description,
        ]), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Suggestions for several products in ONE request (max 10).
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, array{meta_title:string, meta_description:string, description:?string}>
     */
    public function products(Collection $products, bool $withDescription = false, string $keywords = ''): array
    {
        $products = $products->take(10)->values();

        if ($products->isEmpty()) {
            return [];
        }

        $room = $this->titleRoom();
        $list = $products->map(fn (Product $p) => $this->productFacts($p))->implode("\n");

        $prompt = "Write SEO copy for these Village City products, one JSON object per product (keep each \"id\").\n"
            .$this->keywordsLine($keywords)."Products (JSON per line):\n{$list}\n\n"
            ."Requirements per product:\n- meta_title: at most {$room} characters, WITHOUT the brand, format like \"Tomatoes – Wholesale Prices in Kenya\" (vary the wording naturally).\n"
            ."- meta_description: 120 to 155 characters; mention the product and the wholesale/delivery benefit; use only the facts given.\n"
            .($withDescription
                ? "- description: 220 to 420 characters of useful, factual product copy for the product page (uses, handling/storage if given, how it is supplied). Do not state origin, variety, grade, certification or price unless provided. Do not mention payment methods or credit terms, and only name customer types that appear in the verified facts.\n"
                : "- description: return an empty string.\n");

        $item = self::obj(['id' => ['type' => 'INTEGER'], 'meta_title' => self::STR, 'meta_description' => self::STR, 'description' => self::STR]);
        $r = $this->gemini->json($prompt, ['type' => 'ARRAY', 'items' => $item], $this->system(), 0.5);

        $out = [];
        $allowed = $products->pluck('id')->all();

        foreach ($r as $row) {
            $id = (int) ($row['id'] ?? 0);

            if (! in_array($id, $allowed, true)) {
                continue; // ignore anything the model made up
            }

            $out[$id] = [
                'meta_title' => self::fit($row['meta_title'] ?? '', $room),
                'meta_description' => self::fit($row['meta_description'] ?? '', 158),
                'description' => $withDescription ? self::fit($row['description'] ?? '', 600) : null,
            ];
        }

        return $out;
    }

    /** @return array{meta_title:string, meta_description:string, seo_text:string} */
    public function category(ProductCategory $category, string $keywords = ''): array
    {
        $room = $this->titleRoom();
        $items = $category->products()->where('status', 'active')->orderBy('name')->pluck('name')->implode(', ');

        $prompt = "Write SEO copy for the Village City product category page \"{$category->name}\".\n".$this->keywordsLine($keywords)
            ."Category description: {$category->description}\nProducts in this range: {$items}\nCurrent intro text: {$category->seo_text}\n\n"
            ."Requirements:\n- meta_title: at most {$room} characters, WITHOUT the brand.\n- meta_description: 120 to 155 characters.\n"
            .'- seo_text: 60 to 90 words, an introduction for the top of the category page describing the range and who it suits; mention some of the listed products by name.';

        $r = $this->gemini->json($prompt, self::obj(['meta_title' => self::STR, 'meta_description' => self::STR, 'seo_text' => self::STR]), $this->system(), 0.6);

        return [
            'meta_title' => self::fit($r['meta_title'] ?? '', $room),
            'meta_description' => self::fit($r['meta_description'] ?? '', 158),
            'seo_text' => self::fit($r['seo_text'] ?? '', 700),
        ];
    }

    // ---- FAQs, content, keywords ---------------------------------------------------------------------

    /**
     * New FAQ candidates that do not repeat existing questions.
     *
     * @return list<array{question:string, answer:string}>
     */
    public function faqs(int $count = 6, string $topic = ''): array
    {
        $count = max(1, min(10, $count));
        $existing = Faq::orderBy('sort_order')->pluck('question')->implode("\n- ");

        $prompt = "Write {$count} new frequently asked questions with answers for the Village City website FAQ page.\n"
            .($topic !== '' ? 'Focus on: '.trim(strip_tags($topic))."\n" : '')
            ."Existing questions (do not repeat): \n- ".($existing ?: '(none)')."\n\n"
            ."Requirements:\n- Questions are phrased the way buyers or farmers would type them into Google.\n"
            ."- Only write questions that the verified facts answer COMPLETELY. Do not ask about anything the facts do not cover (for example exact delivery fees, payment timing for farmers, or product origins).\n"
            ."- Answers are 30 to 70 words, direct, honest and use ONLY the verified facts; never guess numbers or timings.\n"
            .'- No promotional claims you cannot support.';

        $r = $this->gemini->json($prompt, ['type' => 'ARRAY', 'items' => self::obj(['question' => self::STR, 'answer' => self::STR])], $this->system(), 0.6);

        return collect($r)->map(fn ($row) => [
            'question' => self::fit($row['question'] ?? '', 200),
            'answer' => self::fit($row['answer'] ?? '', 700),
        ])->filter(fn ($f) => mb_strlen($f['question']) >= 8 && mb_strlen($f['answer']) >= 10)->take($count)->values()->all();
    }

    /**
     * Rewrite one editable website text field.
     *
     * @param  string  $type  text | textarea | list | pairs
     */
    public function improve(string $label, string $text, string $type, string $instruction = ''): string
    {
        $format = match ($type) {
            'list' => 'Return one item per line, no bullets or numbering.',
            'pairs' => 'Return one item per line in the form "Title | short description", keeping the same number of lines.',
            'text' => 'Return a single short line (no line breaks).',
            default => 'Return plain text paragraphs.',
        };
        $max = in_array($type, ['text'], true) ? 140 : 1200;

        $prompt = "Improve this website copy field (\"{$label}\") so it is clearer, more specific and better for SEO, while keeping its meaning and roughly its length.\n"
            .($instruction !== '' ? 'Extra guidance: '.trim(strip_tags($instruction))."\n" : '')
            ."{$format}\nDo not add claims that are not in the verified facts or the original text.\n\nCurrent text:\n{$text}";

        $r = $this->gemini->json($prompt, self::obj(['text' => self::STR]), $this->system(), 0.5);
        $result = (string) ($r['text'] ?? '');

        if (in_array($type, ['list', 'pairs', 'textarea'], true)) {
            $lines = array_map(fn ($l) => self::fit($l, 400), preg_split('/\R/', strip_tags($result)));

            return implode("\n", array_filter($lines, fn ($l) => $l !== ''));
        }

        return self::fit($result, $max);
    }

    /**
     * Keyword ideas for a page (shown as clickable suggestions).
     *
     * @return list<array{keyword:string, intent:string}>
     */
    public function keywords(string $page): array
    {
        $def = config("site.pages.{$page}");
        abort_unless($def, 404);

        $prompt = "Suggest 8 realistic search phrases Kenyan buyers or farmers might use that fit the \"{$def['label']}\" page of the Village City website.\n"
            ."Page context:\n".$this->pageContext($page)."\n"
            .'For each give the phrase (2–5 words, lower case) and the search intent: buy, compare, learn or supply.';

        $r = $this->gemini->json($prompt, ['type' => 'ARRAY', 'items' => self::obj(['keyword' => self::STR, 'intent' => self::STR])], $this->system(), 0.7);

        return collect($r)->map(fn ($row) => ['keyword' => Str::lower(self::fit($row['keyword'] ?? '', 60)), 'intent' => Str::lower(self::fit($row['intent'] ?? '', 20))])
            ->filter(fn ($k) => $k['keyword'] !== '')->unique('keyword')->take(8)->values()->all();
    }

    /** Lightweight connectivity check used by the admin "Test connection" button. */
    public function test(): array
    {
        $this->gemini->json('Return {"ok": true}.', self::obj(['ok' => ['type' => 'BOOLEAN']]), null, 0.0);

        return ['model' => $this->gemini->lastModel, 'models' => $this->gemini->models()];
    }
}
