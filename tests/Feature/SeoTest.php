<?php

namespace Tests\Feature;

use App\Livewire\Pages\Seo as SeoPage;
use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Models\User;
use App\Services\SeoAudit;
use App\Support\Schema;
use App\Support\Seo;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, ReferenceDataSeeder::class]);
    }

    protected function admin(): User
    {
        return User::factory()->create()->assignRole('Administrator');
    }

    /** @return list<array> decoded JSON-LD nodes in the page */
    protected function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return array_map(fn ($json) => json_decode(html_entity_decode($json), true, 512, JSON_THROW_ON_ERROR), $m[1]);
    }

    protected function ofType(array $nodes, string $type): ?array
    {
        foreach ($nodes as $node) {
            if (($node['@type'] ?? null) === $type) {
                return $node;
            }
        }

        return null;
    }

    protected function pngUpload(string $name = 'og.png'): UploadedFile
    {
        // 1x1 transparent PNG; real bytes so MIME sniffing and getimagesize() work without GD.
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    }

    // ---- Head tags ----------------------------------------------------------------------------------

    public function test_the_home_page_has_complete_head_metadata(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Village City | Fresh Produce from Kenyan Farms in 24 Hours</title>', $html);
        $this->assertMatchesRegularExpression('#<meta name="description" content="Fresh vegetables, fruit and cereals sourced directly#', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow, max-image-preview:large', $html);
        $this->assertStringContainsString('<html lang="en-KE">', $html);

        foreach (['og:type" content="website', 'og:site_name" content="Village City', 'og:locale" content="en_KE', 'og:title', 'og:description', 'og:url', 'og:image"', 'og:image:width" content="1200', 'og:image:height" content="630', 'og:image:alt'] as $tag) {
            $this->assertStringContainsString('property="'.$tag, $html, "missing {$tag}");
        }

        foreach (['twitter:card" content="summary_large_image', 'twitter:title', 'twitter:description', 'twitter:image"'] as $tag) {
            $this->assertStringContainsString('name="'.$tag, $html, "missing {$tag}");
        }

        $this->assertStringContainsString('images/og/home.jpg', $html);
        $this->assertStringContainsString('rel="manifest"', $html);
        $this->assertStringContainsString('rel="apple-touch-icon"', $html);
        $this->assertStringContainsString('hreflang="en-KE"', $html);
        $this->assertStringContainsString('name="theme-color"', $html);
        $this->assertStringContainsString('rel="preload" as="image"', $html);
        $this->assertSame(1, substr_count($html, '<h1'), 'exactly one <h1> per page');
        $this->assertStringContainsString('Skip to main content', $html);
    }

    public function test_structured_data_is_valid_json_with_organization_and_website(): void
    {
        Setting::put('company_phone', '+254 700 000 000');
        Setting::put('company_email', 'hello@villagecity.test');
        Setting::put('seo.social_facebook', 'https://facebook.com/villagecity');

        $nodes = $this->jsonLd($this->get('/')->getContent());

        $org = $this->ofType($nodes, 'Organization');
        $this->assertSame('Village City', $org['name']);
        $this->assertSame('+254 700 000 000', $org['contactPoint']['telephone']);
        $this->assertSame(['https://facebook.com/villagecity'], $org['sameAs']);
        $this->assertSame('ImageObject', $org['logo']['@type']);

        $site = $this->ofType($nodes, 'WebSite');
        $this->assertSame('SearchAction', $site['potentialAction']['@type']);
        $this->assertStringContainsString('{search_term_string}', $site['potentialAction']['target']['urlTemplate']);
        $this->assertNotNull($this->ofType($nodes, 'WebPage'));
    }

    public function test_json_ld_cannot_break_out_of_its_script_tag(): void
    {
        $json = Schema::encode(['name' => '</script><script>alert(1)</script>']);

        $this->assertStringNotContainsString('</script>', $json);
        $this->assertSame('</script><script>alert(1)</script>', json_decode($json, true)['name']);
    }

    // ---- Products & categories ---------------------------------------------------------------------

    public function test_product_pages_have_their_own_url_metadata_and_product_schema(): void
    {
        $html = $this->get('/produce/tomatoes')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/produce/tomatoes">', $html);
        $this->assertStringContainsString('<title>Tomatoes – Wholesale Prices in Kenya | Village City</title>', html_entity_decode($html));
        $this->assertStringContainsString('property="og:type" content="product"', $html);
        $this->assertStringContainsString('property="product:price:amount" content="70.00"', $html);
        $this->assertStringContainsString('property="product:price:currency" content="KES"', $html);
        $this->assertStringContainsString('images/products/tomatoes.jpg', $html);

        $nodes = $this->jsonLd($html);
        $product = $this->ofType($nodes, 'Product');
        $this->assertSame('Tomatoes', $product['name']);
        $this->assertSame('KES', $product['offers']['priceCurrency']);
        $this->assertSame('70.00', $product['offers']['price']);
        $this->assertContains($product['offers']['availability'], ['https://schema.org/InStock', 'https://schema.org/BackOrder']);
        $this->assertArrayNotHasKey('cost', $product);

        $crumbs = $this->ofType($nodes, 'BreadcrumbList');
        $this->assertSame(['Home', 'Produce', 'Village City Fresh', 'Tomatoes'], array_column($crumbs['itemListElement'], 'name'));
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_custom_product_seo_overrides_the_automatic_text(): void
    {
        Product::where('slug', 'tomatoes')->update(['meta_title' => 'Buy Tomatoes Wholesale', 'meta_description' => 'Farm-fresh tomatoes by the crate, graded and delivered in 24 hours across Kenya.']);

        $html = $this->get('/produce/tomatoes')->getContent();

        $this->assertStringContainsString('<title>Buy Tomatoes Wholesale | Village City</title>', $html);
        $this->assertStringContainsString('content="Farm-fresh tomatoes by the crate', $html);
    }

    public function test_inactive_or_unknown_products_are_404(): void
    {
        Product::where('slug', 'rice')->update(['status' => 'inactive']);

        $this->get('/produce/rice')->assertNotFound();
        $this->get('/produce/not-a-product')->assertNotFound();
    }

    public function test_category_pages_work_and_old_category_links_redirect_permanently(): void
    {
        $category = ProductCategory::where('name', 'Village City Fresh')->first();
        $category->update(['seo_text' => 'Fresh vegetables and fruit sourced daily from smallholder farms.']);

        $html = $this->get('/produce/category/'.$category->slug)->assertOk()->getContent();
        $this->assertStringContainsString('Fresh vegetables and fruit sourced daily', $html);
        $this->assertStringContainsString('canonical" href="'.rtrim(config('app.url'), '/').'/produce/category/'.$category->slug, $html);
        $this->assertNotNull($this->ofType($this->jsonLd($html), 'ItemList'));
        $this->assertStringNotContainsString('Maize', $html, 'only the category products are listed');

        $this->get('/produce?category='.$category->id)->assertStatus(301)->assertRedirect(route('site.category', $category->slug));
    }

    public function test_search_results_are_noindex_and_pagination_gets_its_own_canonical_and_title(): void
    {
        $this->assertStringContainsString('noindex, follow', $this->get('/produce?search=tom')->getContent());

        $page2 = $this->get('/produce?page=2')->getContent();
        $this->assertStringContainsString('– Page 2', $page2);
        $this->assertStringContainsString('canonical" href="'.rtrim(config('app.url'), '/').'/produce?page=2', $page2);
        $this->assertStringContainsString('index, follow', $page2);
    }

    // ---- Sitemap & robots ---------------------------------------------------------------------------

    public function test_the_sitemap_is_valid_xml_listing_public_urls_and_images(): void
    {
        Product::where('slug', 'rice')->update(['status' => 'inactive']);
        Faq::create(['question' => 'How fast is delivery?', 'answer' => 'Within 24 hours.']);

        $response = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'sitemap must be well-formed XML');
        $locs = array_map('strval', (array) $xml->xpath('//*[local-name()="loc"][parent::*[local-name()="url"]]'));
        $base = rtrim(config('app.url'), '/');

        foreach (['/', '/produce', '/about', '/sell-with-us', '/contact', '/faq', '/produce/tomatoes', '/produce/category/village-city-fresh'] as $path) {
            $this->assertContains($path === '/' ? $base.'/' : $base.$path, $locs, "sitemap missing {$path}");
        }

        $this->assertNotContains($base.'/produce/rice', $locs);
        $this->assertStringContainsString('<image:loc>', $response->getContent());
        $this->assertStringContainsString('<lastmod>', $response->getContent());
        $this->assertStringNotContainsString('/dashboard', $response->getContent());
    }

    public function test_noindex_pages_and_an_empty_faq_are_left_out_of_the_sitemap(): void
    {
        $this->assertStringNotContainsString('/faq', $this->get('/sitemap.xml')->getContent(), 'no published FAQs yet');

        Setting::put('seo.page.about.noindex', '1');

        $this->assertStringNotContainsString('/about', $this->get('/sitemap.xml')->getContent());
        $this->assertStringContainsString('noindex', $this->get('/about')->getContent());
    }

    public function test_robots_txt_allows_the_public_site_blocks_the_app_and_links_the_sitemap(): void
    {
        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('User-agent: *', $body);
        $this->assertStringContainsString('Allow: /', $body);
        foreach (['/dashboard', '/login', '/orders', '/livewire', '/storage/inspections/', '/storage/proof-of-delivery/'] as $path) {
            $this->assertStringContainsString('Disallow: '.$path, $body);
        }
        $this->assertStringContainsString('Sitemap: '.rtrim(config('app.url'), '/').'/sitemap.xml', $body);
    }

    public function test_turning_indexing_off_blocks_everything(): void
    {
        Setting::put('seo.allow_indexing', '0');

        $this->assertStringContainsString('Disallow: /', $this->get('/robots.txt')->getContent());
        $this->assertStringNotContainsString('Sitemap:', $this->get('/robots.txt')->getContent());
        $this->assertStringContainsString('noindex, follow', $this->get('/')->getContent());
    }

    public function test_private_areas_send_a_noindex_header_and_public_pages_do_not(): void
    {
        $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertStringContainsString('noindex', $this->get('/login')->getContent());

        $this->actingAs($this->admin())->get('/dashboard')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        foreach (['/', '/produce', '/about', '/faq', '/produce/tomatoes', '/sitemap.xml', '/robots.txt'] as $url) {
            $this->get($url)->assertHeaderMissing('X-Robots-Tag');
        }
    }

    // ---- Admin: access ------------------------------------------------------------------------------

    public function test_only_administrators_and_seo_managers_can_open_the_seo_page(): void
    {
        $this->actingAs($this->admin())->get('/seo-content')->assertOk()->assertSee('SEO health score');
        $this->actingAs(User::factory()->create()->assignRole('Content & SEO Manager'))->get('/seo-content')->assertOk();
        $this->actingAs(User::factory()->create()->assignRole('Sales Manager'))->get('/seo-content')->assertForbidden();
        $this->actingAs(User::factory()->create()->assignRole('Customer'))->get('/seo-content')->assertForbidden();

        $this->assertTrue(\Spatie\Permission\Models\Role::findByName('Administrator')->hasPermissionTo('seo.manage'));
    }

    public function test_the_seo_page_is_in_the_administrators_navigation(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')->assertSee('SEO &amp; Site Content', false);
        $this->actingAs(User::factory()->create()->assignRole('Quality Control Officer'))->get('/help')->assertDontSee('SEO &amp; Site Content', false);
    }

    // ---- Admin: global settings ---------------------------------------------------------------------

    public function test_saving_global_seo_changes_what_visitors_see(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(SeoPage::class)
            ->set('global.site_name', 'Village City Kenya')
            ->set('global.title_template', '%s — Village City Kenya')
            ->set('global.twitter_handle', '@villagecityke')
            ->set('global.google_site_verification', 'abc123token')
            ->set('global.bing_site_verification', 'bing456token')
            ->set('global.ga4_id', 'G-ABC123XYZ9')
            ->set('global.canonical_base', 'https://www.villagecity.co.ke')
            ->call('saveGlobal')->assertHasNoErrors();

        auth()->logout();
        $html = $this->get('/produce/tomatoes')->getContent();

        $this->assertStringContainsString('og:site_name" content="Village City Kenya', $html);
        $this->assertStringContainsString('twitter:site" content="@villagecityke', $html);
        $this->assertStringContainsString('google-site-verification" content="abc123token', $html);
        $this->assertStringContainsString('msvalidate.01" content="bing456token', $html);
        $this->assertStringContainsString('canonical" href="https://www.villagecity.co.ke/produce/tomatoes', $html);
        $this->assertStringContainsString('googletagmanager.com/gtag/js?id=G-ABC123XYZ9', $html);
        $this->assertStringContainsString('https://www.villagecity.co.ke/sitemap.xml', $this->get('/robots.txt')->getContent());
        $this->assertStringContainsString('https://www.villagecity.co.ke/produce/tomatoes', $this->get('/sitemap.xml')->getContent());
    }

    public function test_analytics_never_loads_for_signed_in_staff(): void
    {
        Setting::put('seo.ga4_id', 'G-ABC123XYZ9');

        $this->assertStringContainsString('googletagmanager', $this->get('/')->getContent());
        $this->actingAs($this->admin());
        $this->assertStringNotContainsString('googletagmanager', $this->get('/')->getContent());
    }

    public function test_global_seo_is_validated(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(SeoPage::class)
            ->set('global.ga4_id', 'not-an-id')->set('global.title_template', 'no placeholder')->set('global.canonical_base', 'not a url')->set('global.locale', 'english')
            ->call('saveGlobal')->assertHasErrors(['global.ga4_id', 'global.title_template', 'global.canonical_base', 'global.locale']);
    }

    public function test_a_default_sharing_image_can_be_uploaded_and_is_used_in_og_tags(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        Livewire::test(SeoPage::class)->set('files.g__default_og_image', $this->pngUpload())->call('saveGlobal')->assertHasNoErrors();

        $this->assertNotNull($stored = Setting::get('seo.default_og_image'));
        Storage::disk('public')->assertExists($stored);

        auth()->logout();
        // Pages without their own image (e.g. a product with no photo) fall back to the uploaded default.
        Product::where('slug', 'wheat')->update(['image_path' => null]);
        $this->assertStringContainsString('storage/'.$stored, $this->get('/produce/wheat')->getContent());
    }

    public function test_non_images_are_rejected_as_sharing_images(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(SeoPage::class)->set('files.g__default_og_image', UploadedFile::fake()->createWithContent('x.pdf', '%PDF-1.4 fake'))
            ->call('saveGlobal')->assertHasErrors(['files.g__default_og_image']);
    }

    // ---- Admin: pages and content -------------------------------------------------------------------

    public function test_page_level_seo_overrides_title_description_and_social_tags(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(SeoPage::class)
            ->set('tab', 'pages')
            ->set('pages.about.title', 'Our Story – Farm to City Supply in Kenya')
            ->set('pages.about.description', 'Learn how Village City pays farmers fairly and delivers fresh produce to Kenyan businesses within 24 hours.')
            ->set('pages.about.og_title', 'Meet Village City')
            ->set('pages.about.og_description', 'Fair prices for farmers, fresh produce for the city.')
            ->call('savePage', 'about')->assertHasNoErrors();

        auth()->logout();
        $html = $this->get('/about')->getContent();

        $this->assertStringContainsString('<title>Our Story – Farm to City Supply in Kenya | Village City</title>', $html);
        $this->assertStringContainsString('content="Learn how Village City pays farmers fairly', $html);
        $this->assertStringContainsString('og:title" content="Meet Village City', $html);
        $this->assertStringContainsString('twitter:description" content="Fair prices for farmers', $html);
    }

    public function test_page_seo_rejects_titles_and_descriptions_that_are_too_short(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(SeoPage::class)->set('pages.about.title', 'Hi')->set('pages.about.description', 'Too short')
            ->call('savePage', 'about')->assertHasErrors(['pages.about.title', 'pages.about.description']);
    }

    public function test_saving_unchanged_defaults_stores_nothing_so_future_defaults_still_apply(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(SeoPage::class)->call('savePage', 'home')->call('saveGlobal')->call('saveContent', 'home');

        $this->assertSame(0, Setting::where('key', 'like', 'seo.%')->orWhere('key', 'like', 'content.%')->count());
    }

    public function test_editing_site_content_changes_the_public_pages_and_blank_restores_the_default(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(SeoPage::class)->set('tab', 'content')
            ->set('content.home.hero_title', 'Kenya’s freshest produce,')->set('content.home.hero_title_accent', 'at your door.')
            ->set('content.home.cta_heading', 'Let us supply your kitchen')
            ->call('saveContent', 'home')->assertHasNoErrors();

        auth()->logout();
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('Kenya’s freshest produce,', $html);
        $this->assertStringContainsString('at your door.', $html);
        $this->assertStringContainsString('Let us supply your kitchen', $html);
        $this->assertStringNotContainsString('From the farm to the city, <span', $html);

        $this->actingAs($this->admin());
        Livewire::test(SeoPage::class)->set('content.home.hero_title', '')->call('saveContent', 'home');
        auth()->logout();
        $this->assertStringContainsString('From the farm to the city,', $this->get('/')->getContent());
    }

    public function test_list_and_pair_content_fields_render_as_lists(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(SeoPage::class)->set('content.about.what_we_do', "Buy from farmers\nSell to cities")
            ->set('content.about.values', "Trust | We keep our word.\nSpeed | Farm to city in a day.")->call('saveContent', 'about');

        auth()->logout();
        $html = $this->get('/about')->getContent();
        $this->assertStringContainsString('Buy from farmers', $html);
        $this->assertStringContainsString('Sell to cities', $html);
        $this->assertStringContainsString('<h3 class="font-semibold text-forest-900">Trust</h3>', $html);
        $this->assertStringContainsString('We keep our word.', $html);
    }

    public function test_the_announcement_bar_is_controlled_from_the_admin(): void
    {
        $this->assertStringNotContainsString('role="status"', $this->get('/')->getContent());

        $this->actingAs($this->admin());
        Livewire::test(SeoPage::class)->set('content.site.announcement_enabled', true)->set('content.site.announcement_text', 'Free delivery on orders over KES 20,000')
            ->set('content.site.announcement_link', 'https://example.com/offer')->call('saveContent', 'site')->assertHasNoErrors();
        auth()->logout();

        $html = $this->get('/about')->getContent();
        $this->assertStringContainsString('Free delivery on orders over KES 20,000', $html);
        $this->assertStringContainsString('href="https://example.com/offer"', $html);
    }

    public function test_content_is_escaped_so_admins_cannot_inject_markup(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(SeoPage::class)->set('content.home.hero_text', '<script>alert("x")</script> Fresh produce')->call('saveContent', 'home');
        auth()->logout();

        $html = $this->get('/')->getContent();
        $this->assertStringNotContainsString('<script>alert("x")</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; Fresh produce', $html);
    }

    // ---- FAQs ---------------------------------------------------------------------------------------

    public function test_faqs_are_managed_in_the_admin_and_publish_faq_structured_data(): void
    {
        $this->actingAs($this->admin());

        $page = Livewire::test(SeoPage::class)->set('tab', 'faqs')
            ->call('editFaq')->set('faqForm.question', 'Do you deliver on Sundays?')->set('faqForm.answer', 'Yes, by arrangement for institutional customers.')->set('faqForm.show_on_home', true)
            ->call('saveFaq')->assertHasNoErrors();
        $page->call('editFaq')->set('faqForm.question', 'Draft question here?')->set('faqForm.answer', 'This answer is not live yet.')->set('faqForm.is_published', false)->call('saveFaq');

        auth()->logout();
        $faqHtml = $this->get('/faq')->assertOk()->getContent();
        $this->assertStringContainsString('Do you deliver on Sundays?', $faqHtml);
        $this->assertStringNotContainsString('Draft question here?', $faqHtml);

        $schema = $this->ofType($this->jsonLd($faqHtml), 'FAQPage');
        $this->assertCount(1, $schema['mainEntity']);
        $this->assertSame('Do you deliver on Sundays?', $schema['mainEntity'][0]['name']);

        $this->assertStringContainsString('Do you deliver on Sundays?', $this->get('/')->getContent());
        $this->assertStringContainsString('/faq', $this->get('/sitemap.xml')->getContent());
    }

    public function test_faqs_can_be_reordered_and_deleted(): void
    {
        $a = Faq::create(['question' => 'First question?', 'answer' => 'Answer one here.', 'sort_order' => 10]);
        $b = Faq::create(['question' => 'Second question?', 'answer' => 'Answer two here.', 'sort_order' => 20]);
        $this->actingAs($this->admin());

        Livewire::test(SeoPage::class)->call('moveFaq', $b->id, -1);
        $this->assertSame(['Second question?', 'First question?'], Faq::published()->pluck('question')->all());

        Livewire::test(SeoPage::class)->call('deleteFaq', $a->id);
        $this->assertDatabaseMissing('faqs', ['id' => $a->id]);
    }

    // ---- Catalogue SEO ------------------------------------------------------------------------------

    public function test_product_slug_and_meta_can_be_edited_and_slugs_must_stay_unique(): void
    {
        $this->actingAs($this->admin());
        $tomatoes = Product::where('slug', 'tomatoes')->first();
        $onions = Product::where('slug', 'onions')->first();

        $page = Livewire::test(SeoPage::class)->set('tab', 'catalogue')->set('catSearch', 'Tomatoes');
        $page->set("catalogue.{$tomatoes->id}.slug", 'fresh-tomatoes')->set("catalogue.{$tomatoes->id}.meta_title", 'Fresh Tomatoes Kenya')->call('saveProduct', $tomatoes->id)->assertHasNoErrors();

        $this->assertSame('fresh-tomatoes', $tomatoes->refresh()->slug);

        auth()->logout();
        $this->get('/produce/fresh-tomatoes')->assertOk()->assertSee('Fresh Tomatoes Kenya', false);

        $this->actingAs($this->admin());
        $dup = Livewire::test(SeoPage::class)->set('tab', 'catalogue')->set('catSearch', 'Onions');
        $dup->set("catalogue.{$onions->id}.slug", 'fresh-tomatoes')->call('saveProduct', $onions->id)->assertHasErrors(["catalogue.{$onions->id}.slug"]);
        $dup->set("catalogue.{$onions->id}.slug", 'Bad Slug!')->call('saveProduct', $onions->id)->assertHasErrors(["catalogue.{$onions->id}.slug"]);
    }

    public function test_category_seo_text_is_editable(): void
    {
        $this->actingAs($this->admin());
        $category = ProductCategory::where('name', 'Village City Fresh')->first();

        Livewire::test(SeoPage::class)->set('tab', 'catalogue')
            ->set("categorySeo.{$category->id}.seo_text", 'Sourced every morning from Western Kenya.')->set("categorySeo.{$category->id}.meta_title", 'Fresh Vegetables Wholesale Kenya')
            ->call('saveCategory', $category->id)->assertHasNoErrors();

        auth()->logout();
        $this->get('/produce/category/'.$category->slug)->assertSee('Sourced every morning from Western Kenya.')->assertSee('Fresh Vegetables Wholesale Kenya', false);
    }

    public function test_slugs_are_generated_unique_and_stable(): void
    {
        $a = Product::create(['name' => 'Sweet Corn', 'product_category_id' => ProductCategory::first()->id, 'unit' => 'kg']);
        $b = Product::create(['name' => 'Sweet Corn', 'product_category_id' => ProductCategory::first()->id, 'unit' => 'kg']);

        $this->assertSame('sweet-corn', $a->slug);
        $this->assertSame('sweet-corn-2', $b->slug);

        $a->update(['name' => 'Sweet Corn Cobs']);
        $this->assertSame('sweet-corn', $a->refresh()->slug, 'renaming must not change a published URL');
    }

    // ---- Audit --------------------------------------------------------------------------------------

    public function test_the_health_check_flags_problems_and_clears_them_when_fixed(): void
    {
        $audit = app(SeoAudit::class)->run();
        $titles = array_column($audit['checks'], 'title');

        $this->assertContains('Canonical URL points to a local address', $titles);
        $this->assertSame('bad', collect($audit['checks'])->firstWhere('title', 'Canonical URL points to a local address')['status']);
        $this->assertGreaterThan(0, $audit['counts']['warn']);

        Setting::put('seo.canonical_base', 'https://www.villagecity.co.ke');
        Setting::put('seo.google_site_verification', 'token');
        Setting::put('seo.social_facebook', 'https://facebook.com/villagecity');
        Setting::put('company_phone', '0700');
        Setting::put('company_email', 'a@b.co');
        Setting::put('company_address', 'Nairobi');
        foreach (config('site.starter_faqs') as [$q, $a]) {
            Faq::create(['question' => $q, 'answer' => $a]);
        }

        $after = app(SeoAudit::class)->run();

        $this->assertNotContains('Canonical URL points to a local address', array_column($after['checks'], 'title'));
        $this->assertGreaterThan($audit['score'], $after['score']);
    }

    public function test_the_default_page_titles_and_descriptions_pass_the_health_check(): void
    {
        $titles = array_column(app(SeoAudit::class)->run()['checks'], 'title');

        $this->assertContains('All page titles and descriptions look good', $titles);

        foreach (config('site.pages') as $key => $page) {
            $c = Seo::pageConfig($key);
            $this->assertLessThanOrEqual(60, mb_strlen(Seo::title($c['title'])), "{$key} title too long");
            $this->assertGreaterThanOrEqual(70, mb_strlen($c['description']), "{$key} description too short");
            $this->assertLessThanOrEqual(160, mb_strlen($c['description']), "{$key} description too long");
        }
    }

    public function test_blocking_indexing_is_reported_as_critical(): void
    {
        Setting::put('seo.allow_indexing', '0');

        $check = collect(app(SeoAudit::class)->run()['checks'])->firstWhere('title', 'Search engines are blocked');

        $this->assertSame('bad', $check['status']);
    }

    // ---- Assets -------------------------------------------------------------------------------------

    public function test_og_images_icons_and_manifest_exist(): void
    {
        foreach (['default', 'home', 'produce', 'about', 'sell', 'contact', 'faq'] as $name) {
            $path = public_path("images/og/{$name}.jpg");
            $this->assertFileExists($path);
            $this->assertSame([1200, 630], array_slice(getimagesize($path), 0, 2), "{$name}.jpg must be 1200×630");
        }

        foreach (['favicon.ico', 'favicon.svg', 'apple-touch-icon.png', 'icons/icon-192.png', 'icons/icon-512.png', 'icons/icon-maskable-512.png', 'site.webmanifest'] as $file) {
            $this->assertFileExists(public_path($file));
        }

        $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true);
        $this->assertSame('Village City', $manifest['name']);
        $this->assertCount(3, $manifest['icons']);
        $this->assertFileDoesNotExist(public_path('robots.txt'), 'robots.txt must be served dynamically');
    }

    public function test_starter_faqs_exist_and_seeder_is_idempotent(): void
    {
        $this->seed(\Database\Seeders\FaqSeeder::class);
        $count = Faq::count();
        $this->assertGreaterThanOrEqual(8, $count);

        $this->seed(\Database\Seeders\FaqSeeder::class);
        $this->assertSame($count, Faq::count());
    }

    public function test_seo_helper_builds_titles_and_excerpts_sensibly(): void
    {
        $this->assertSame('About | Village City', Seo::title('About'));
        $this->assertSame('Village City – Home', Seo::title('Village City – Home'), 'brand is not repeated');
        $this->assertSame('Hello world', Seo::excerpt("<p>Hello\n  world</p>"));
        $this->assertLessThanOrEqual(158, mb_strlen(Seo::excerpt(str_repeat('word ', 100))));
    }
}
