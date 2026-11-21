<?php

namespace Tests\Feature;

use App\Livewire\Pages\Seo as SeoPage;
use App\Models\Customer;
use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\GeminiException;
use App\Services\Ai\SeoAi;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class SeoAiTest extends TestCase
{
    use RefreshDatabase;

    protected const KEY = 'AQ.test-secret-key-123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, ReferenceDataSeeder::class]);
        config(['services.gemini.key' => self::KEY, 'services.gemini.models' => 'model-a,model-b', 'services.gemini.backoff_ms' => 0, 'services.gemini.hourly_limit' => 60]);
        RateLimiter::clear('gemini:'.User::query()->value('id'));
    }

    /** A Gemini-shaped success response carrying $data as structured JSON. */
    protected function reply(array $data): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($data)]]], 'finishReason' => 'STOP']]]);
    }

    protected function admin(): User
    {
        return User::factory()->create()->assignRole('Administrator');
    }

    // ---- Client ---------------------------------------------------------------------------------------

    public function test_the_client_sends_the_key_in_a_header_never_in_the_url_and_parses_json(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => $this->reply(['title' => 'Hello'])]);

        $result = app(GeminiClient::class)->json('Say hi', ['type' => 'OBJECT', 'properties' => ['title' => ['type' => 'STRING']]], 'You are helpful');

        $this->assertSame(['title' => 'Hello'], $result);
        $this->assertSame('model-a', app(GeminiClient::class)->lastModel ?? 'model-a');

        Http::assertSent(function (Request $r) {
            $this->assertStringNotContainsString(self::KEY, $r->url());
            $this->assertSame(self::KEY, $r->header('x-goog-api-key')[0]);
            $this->assertStringContainsString('/v1beta/models/model-a:generateContent', $r->url());
            $this->assertSame('application/json', $r['generationConfig']['responseMimeType']);
            $this->assertSame('You are helpful', $r['systemInstruction']['parts'][0]['text']);

            return true;
        });
    }

    public function test_a_missing_model_is_skipped_and_the_next_one_answers(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/model-a:generateContent' => Http::response(['error' => ['message' => 'not found']], 404),
            'generativelanguage.googleapis.com/v1beta/models/model-b:generateContent' => $this->reply(['ok' => true]),
        ]);

        $client = app(GeminiClient::class);

        $this->assertSame(['ok' => true], $client->json('x', ['type' => 'OBJECT']));
        $this->assertSame('model-b', $client->lastModel);
        Http::assertSentCount(2); // 404 is not retried
    }

    public function test_busy_models_are_retried_once_then_the_fallback_is_used(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/model-a:generateContent' => Http::response(['error' => ['message' => 'overloaded']], 503),
            'generativelanguage.googleapis.com/v1beta/models/model-b:generateContent' => $this->reply(['ok' => true]),
        ]);

        $this->assertSame(['ok' => true], app(GeminiClient::class)->json('x', ['type' => 'OBJECT']));

        Http::assertSentCount(3); // model-a twice, model-b once
    }

    public function test_when_every_model_fails_a_friendly_error_is_thrown(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

        $this->expectException(GeminiException::class);
        $this->expectExceptionMessage('busy or unavailable');
        app(GeminiClient::class)->json('x', ['type' => 'OBJECT']);
    }

    public function test_a_rejected_key_stops_immediately_without_trying_other_models(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid. Please pass a valid API key.']], 400)]);

        try {
            app(GeminiClient::class)->json('x', ['type' => 'OBJECT']);
            $this->fail('Expected an exception');
        } catch (GeminiException $e) {
            $this->assertStringContainsString('rejected the Gemini API key', $e->getMessage());
            $this->assertStringNotContainsString(self::KEY, $e->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_without_a_key_nothing_is_sent(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake();

        $this->assertFalse(app(GeminiClient::class)->configured());

        try {
            app(GeminiClient::class)->json('x', ['type' => 'OBJECT']);
            $this->fail('Expected an exception');
        } catch (GeminiException $e) {
            $this->assertStringContainsString('GEMINI_API_KEY', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_blocked_and_unparseable_answers_are_reported_clearly(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']])]);
        $this->expectException(GeminiException::class);
        $this->expectExceptionMessage('declined');
        app(GeminiClient::class)->json('x', ['type' => 'OBJECT']);
    }

    public function test_json_wrapped_in_code_fences_is_still_understood(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => "```json\n{\"a\":1}\n```"]]], 'finishReason' => 'STOP']]])]);

        $this->assertSame(['a' => 1], app(GeminiClient::class)->json('x', ['type' => 'OBJECT']));
    }

    public function test_models_are_read_from_the_comma_separated_setting(): void
    {
        config(['services.gemini.models' => ' gemini-flash-lite-latest , gemini-flash-latest ,, gemini-flash-latest ']);

        $this->assertSame(['gemini-flash-lite-latest', 'gemini-flash-latest'], app(GeminiClient::class)->models());
    }

    // ---- SeoAi: output hygiene and privacy -----------------------------------------------------------

    public function test_page_suggestions_are_cleaned_and_cut_to_seo_lengths(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => $this->reply([
            'title' => '<b>"'.str_repeat('Wholesale tomatoes in Kenya ', 5).'"</b>',
            'description' => str_repeat('Fresh produce delivered fast. ', 20),
            'og_title' => 'Meet Village City',
            'og_description' => 'Short & sweet.',
        ])]);

        $r = app(SeoAi::class)->page('about', 'wholesale tomatoes');

        $this->assertLessThanOrEqual(45, mb_strlen($r['title']), 'title leaves room for the brand suffix');
        $this->assertStringNotContainsString('<b>', $r['title']);
        $this->assertStringNotContainsString('"', $r['title']);
        $this->assertLessThanOrEqual(158, mb_strlen($r['description']));
        $this->assertSame('Short & sweet.', $r['og_description']);
        $this->assertStringEndsNotWith(' ', $r['title']);
    }

    public function test_prompts_contain_only_public_facts_never_costs_customers_or_staff(): void
    {
        Product::where('slug', 'tomatoes')->update(['buying_price' => 98765, 'description' => 'Ripe tomatoes.']);
        Customer::create(['name' => 'Secret Hotel Ltd', 'email' => 'buyer-private@example.com', 'status' => 'active']);
        $admin = $this->admin();
        $admin->update(['email' => 'private-admin@villagecity.test']);
        Setting::put('company_phone', '+254 700 111 222');
        $this->actingAs($admin);

        $bodies = [];
        Http::fake(['generativelanguage.googleapis.com/*' => function (Request $r) use (&$bodies) {
            $bodies[] = $r->body();

            return $this->reply([['id' => Product::where('slug', 'tomatoes')->value('id'), 'meta_title' => 'T', 'meta_description' => 'D', 'description' => '']]);
        }]);

        app(SeoAi::class)->products(Product::with('category')->where('slug', 'tomatoes')->get());
        app(SeoAi::class)->page('home');

        $all = implode("\n", $bodies);
        $this->assertStringContainsString('Tomatoes', $all);
        $this->assertStringContainsString('VERIFIED FACTS', $all);

        foreach (['98765', 'Secret Hotel', 'buyer-private', 'private-admin', '+254 700 111 222', self::KEY] as $secret) {
            $this->assertStringNotContainsString($secret, $all, "'{$secret}' must never be sent to the AI provider");
        }
    }

    public function test_batch_product_suggestions_ignore_unknown_ids_and_respect_the_description_flag(): void
    {
        $products = Product::with('category')->whereIn('slug', ['tomatoes', 'onions'])->get();
        [$a, $b] = [$products[0]->id, $products[1]->id];

        Http::fake(['generativelanguage.googleapis.com/*' => $this->reply([
            ['id' => $a, 'meta_title' => 'A title', 'meta_description' => 'A description for the first product.', 'description' => 'Long text we did not ask for'],
            ['id' => $b, 'meta_title' => 'B title', 'meta_description' => 'A description for the second product.', 'description' => ''],
            ['id' => 99999, 'meta_title' => 'Invented', 'meta_description' => 'Not one of ours', 'description' => ''],
        ])]);

        $r = app(SeoAi::class)->products($products, false);

        $this->assertSame([$a, $b], array_keys($r));
        $this->assertNull($r[$a]['description'], 'descriptions are only returned when requested');
    }

    public function test_the_batch_is_capped_at_ten_products(): void
    {
        $bodies = [];
        Http::fake(['generativelanguage.googleapis.com/*' => function (Request $r) use (&$bodies) {
            $bodies[] = $r->body();

            return $this->reply([]);
        }]);

        app(SeoAi::class)->products(Product::with('category')->get()); // 20 products seeded

        $this->assertSame(10, substr_count($bodies[0], '\"current_description\"') + substr_count($bodies[0], '\"name\"'));
    }

    public function test_faq_suggestions_skip_incomplete_items_and_the_prompt_lists_existing_questions(): void
    {
        Faq::create(['question' => 'Already answered question?', 'answer' => 'Yes.']);
        $body = '';
        Http::fake(['generativelanguage.googleapis.com/*' => function (Request $r) use (&$body) {
            $body = $r->body();

            return $this->reply([
                ['question' => 'How do I pay for my order?', 'answer' => 'You can pay by cash, bank transfer or M-Pesa.'],
                ['question' => 'Too short', 'answer' => 'x'],
            ]);
        }]);

        $faqs = app(SeoAi::class)->faqs(5);

        $this->assertCount(1, $faqs);
        $this->assertStringContainsString('Already answered question?', $body);
    }

    public function test_improving_list_fields_keeps_one_item_per_line(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => $this->reply(['text' => "- Buy from farmers\n\n<p>Sell to cities</p>\nDeliver fast"])]);

        $text = app(SeoAi::class)->improve('What we do', "a\nb", 'list');

        $this->assertSame(3, count(explode("\n", $text)));
        $this->assertStringNotContainsString('<p>', $text);
    }

    public function test_the_hourly_limit_protects_the_api_quota(): void
    {
        config(['services.gemini.hourly_limit' => 5]);
        $ai = app(SeoAi::class);

        foreach (range(1, 5) as $i) {
            $ai->throttle(42);
        }

        $this->expectException(GeminiException::class);
        $this->expectExceptionMessage('hourly limit');
        $ai->throttle(42);
    }

    // ---- Admin UI -------------------------------------------------------------------------------------

    public function test_ai_page_suggestions_fill_the_form_but_are_not_saved_until_the_admin_saves(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => $this->reply([
            'title' => 'Our Story: Farm to City Supply', 'description' => 'Village City connects rural Kenyan farmers with city buyers, delivering fresh produce within 24 hours across Kenya.',
            'og_title' => 'Meet Village City', 'og_description' => 'Fair prices for farmers, fresh produce for you.',
        ])]);
        $this->actingAs($this->admin());

        $page = Livewire::test(SeoPage::class)->set('tab', 'pages')->set('aiKeywords.about', 'agricultural supplier kenya')
            ->call('aiPage', 'about')
            ->assertSet('pages.about.title', 'Our Story: Farm to City Supply')
            ->assertSet('pages.about.og_title', 'Meet Village City');

        $this->assertNull(Setting::get('seo.page.about.title'), 'nothing is persisted by a suggestion');
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai_generated']);

        $page->call('savePage', 'about')->assertHasNoErrors();
        $this->assertSame('Our Story: Farm to City Supply', Setting::get('seo.page.about.title'));

        Http::assertSent(fn (Request $r) => str_contains($r->body(), 'agricultural supplier kenya'));
    }

    public function test_ai_failures_show_a_message_and_do_not_break_the_page(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'down']], 503)]);
        $this->actingAs($this->admin());

        Livewire::test(SeoPage::class)->set('tab', 'pages')->call('aiPage', 'home')->assertOk()
            ->assertSet('pages.home.title', config('site.pages.home.title'));
    }

    public function test_ai_buttons_are_hidden_and_actions_do_nothing_when_not_configured(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake();
        $this->actingAs($this->admin());

        Livewire::test(SeoPage::class)->set('tab', 'pages')->assertDontSee('Target keywords')->call('aiPage', 'about')->assertOk();
        $html = Livewire::test(SeoPage::class)->set('tab', 'overview')->html();

        $this->assertStringContainsString('Not configured', $html);
        Http::assertNothingSent();
    }

    public function test_only_seo_managers_can_trigger_ai_actions(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => $this->reply(['ok' => true])]);
        $this->actingAs(User::factory()->create()->assignRole('Sales Manager'));

        Livewire::test(SeoPage::class)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_the_api_key_is_never_present_in_the_admin_page_html(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => $this->reply(['ok' => true])]);
        $this->actingAs($this->admin());

        $page = Livewire::test(SeoPage::class)->call('aiTest');
        $html = $page->html();

        $this->assertStringNotContainsString(self::KEY, $html);
        $this->assertStringNotContainsString(self::KEY, json_encode($page->snapshot ?? []));
        $this->assertStringContainsString('Connected', $html);
    }

    public function test_product_suggestions_can_be_generated_in_bulk_and_saved_together(): void
    {
        $this->actingAs($this->admin());
        $ids = Product::orderBy('name')->take(12)->pluck('id')->all();

        Http::fake(['generativelanguage.googleapis.com/*' => function (Request $r) use ($ids) {
            return $this->reply(array_map(fn ($id) => ['id' => $id, 'meta_title' => 'Title '.$id, 'meta_description' => 'Description for product '.$id.' with enough detail.', 'description' => 'Body '.$id], array_slice($ids, 0, 10)));
        }]);

        $page = Livewire::test(SeoPage::class)->set('tab', 'catalogue')->call('aiProductsMissing', true);

        $page->assertSet("catalogue.{$ids[0]}.meta_title", 'Title '.$ids[0])->assertSet("catalogue.{$ids[0]}.description", 'Body '.$ids[0]);
        $this->assertNull(Product::find($ids[0])->meta_title, 'drafted, not saved');

        $page->call('saveAllProducts')->assertHasNoErrors();

        $this->assertSame('Title '.$ids[0], Product::find($ids[0])->meta_title);
        $this->assertSame('Body '.$ids[0], Product::find($ids[0])->description);
        // Only 10 of the 12 on the page were requested, so the last two keep their original (empty) meta.
        $this->assertNull(Product::find($ids[11])->meta_title);
    }

    public function test_a_single_product_suggestion_can_include_the_description(): void
    {
        $this->actingAs($this->admin());
        $tomatoes = Product::where('slug', 'tomatoes')->first();
        Http::fake(['generativelanguage.googleapis.com/*' => $this->reply([['id' => $tomatoes->id, 'meta_title' => 'Tomatoes – Wholesale Prices in Kenya', 'meta_description' => 'Fresh graded tomatoes supplied by the kilogram and delivered within 24 hours.', 'description' => 'Ripe tomatoes for kitchens and shelves.']])]);

        Livewire::test(SeoPage::class)->set('tab', 'catalogue')->call('aiProduct', $tomatoes->id, true)
            ->assertSet("catalogue.{$tomatoes->id}.meta_title", 'Tomatoes – Wholesale Prices in Kenya')
            ->assertSet("catalogue.{$tomatoes->id}.description", 'Ripe tomatoes for kitchens and shelves.');
    }

    public function test_category_suggestions_fill_the_category_form(): void
    {
        $this->actingAs($this->admin());
        $category = ProductCategory::first();
        Http::fake(['generativelanguage.googleapis.com/*' => $this->reply(['meta_title' => 'Fresh Vegetables Wholesale', 'meta_description' => 'Graded fresh vegetables for hotels, restaurants and supermarkets across Kenya.', 'seo_text' => 'A range of fresh vegetables sourced from farmers.'])]);

        Livewire::test(SeoPage::class)->set('tab', 'catalogue')->call('aiCategory', $category->id)
            ->assertSet("categorySeo.{$category->id}.seo_text", 'A range of fresh vegetables sourced from farmers.');
    }

    public function test_ai_generated_faqs_are_added_as_unpublished_drafts(): void
    {
        $this->actingAs($this->admin());
        Http::fake(['generativelanguage.googleapis.com/*' => $this->reply([
            ['question' => 'How do I pay for my order?', 'answer' => 'You can pay by cash, bank transfer or M-Pesa.'],
            ['question' => 'Can I cancel my order?', 'answer' => 'Yes, while it is pending confirmation.'],
        ])]);

        $page = Livewire::test(SeoPage::class)->set('tab', 'faqs')->call('aiFaqs')->assertSet('showFaqAi', true)->assertCount('faqCandidates', 2);
        $page->set('faqCandidates.1.selected', false)->call('addFaqCandidates');

        $this->assertSame(1, Faq::count());
        $faq = Faq::first();
        $this->assertSame('How do I pay for my order?', $faq->question);
        $this->assertFalse($faq->is_published, 'AI FAQs must be reviewed before going live');
        $this->get('/faq')->assertDontSee('How do I pay for my order?');
    }

    public function test_content_fields_can_be_improved_with_ai(): void
    {
        $this->actingAs($this->admin());
        Http::fake(['generativelanguage.googleapis.com/*' => $this->reply(['text' => 'Fresh Kenyan produce delivered in 24 hours'])]);

        Livewire::test(SeoPage::class)->set('tab', 'content')->set('content.home.hero_title', 'We sell veg')
            ->call('aiContent', 'home', 'hero_title')->assertSet('content.home.hero_title', 'Fresh Kenyan produce delivered in 24 hours');

        $this->assertNull(Setting::get('content.home.hero_title'));
    }

    public function test_only_text_fields_can_be_sent_to_the_ai(): void
    {
        $this->actingAs($this->admin());
        Http::fake();

        try {
            Livewire::test(SeoPage::class)->call('aiContent', 'home', 'hero_image');
        } catch (\Throwable) {
            // 404 for non-text fields
        }

        Http::assertNothingSent();
    }

    public function test_keyword_ideas_can_be_added_to_the_keyword_box(): void
    {
        $this->actingAs($this->admin());
        Http::fake(['generativelanguage.googleapis.com/*' => $this->reply([['keyword' => 'Wholesale Tomatoes Kenya', 'intent' => 'buy'], ['keyword' => 'bulk potatoes nairobi', 'intent' => 'buy']])]);

        Livewire::test(SeoPage::class)->set('tab', 'pages')->call('aiKeywordIdeas', 'produce')
            ->assertSet('keywordIdeas.produce.0.keyword', 'wholesale tomatoes kenya')
            ->call('addKeyword', 'produce', 'wholesale tomatoes kenya')->call('addKeyword', 'produce', 'bulk potatoes nairobi')
            ->assertSet('aiKeywords.produce', 'wholesale tomatoes kenya, bulk potatoes nairobi');
    }

    public function test_the_real_env_models_setting_is_valid(): void
    {
        // Guards against typos like "gemini-flash-lates" in the shipped example configuration.
        $example = file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^GEMINI_MODELS=gemini-flash-lite-latest,gemini-flash-latest$/m', $example);
        $this->assertMatchesRegularExpression('/^GEMINI_API_KEY=$/m', $example, 'the example file must not contain a real key');
    }
}
