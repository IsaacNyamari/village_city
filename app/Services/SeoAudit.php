<?php

namespace App\Services;

use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Support\Seo;

/**
 * Runs a quick technical and content SEO health check and returns a score with actionable items.
 */
class SeoAudit
{
    /**
     * @return array{score:int, checks:list<array{status:string, title:string, detail:string, tab:string}>, counts:array<string,int>, urls:int}
     */
    public function run(): array
    {
        $checks = [];
        $add = function (string $status, string $title, string $detail, string $tab = 'overview') use (&$checks) {
            $checks[] = compact('status', 'title', 'detail', 'tab');
        };

        // ---- Technical -------------------------------------------------------------------------
        if (! Seo::indexable()) {
            $add('bad', 'Search engines are blocked', 'Indexing is switched off, so robots.txt disallows everything and pages send “noindex”. Turn it on in Global SEO when the site is live.', 'global');
        } else {
            $add('ok', 'Search engines may index the site', 'robots.txt allows crawling of the public pages and points to the sitemap.', 'global');
        }

        if (! app()->environment('production')) {
            $add('warn', 'Application is not in production mode', 'APP_ENV is “'.app()->environment().'”. Set APP_ENV=production and APP_DEBUG=false on the live server.', 'overview');
        }

        $base = Seo::baseUrl();
        $host = (string) parse_url($base, PHP_URL_HOST);

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.test') || str_ends_with($host, '.local')) {
            $add('bad', 'Canonical URL points to a local address', 'Canonical links, the sitemap and social previews currently use '.$base.'. Set the live address (https://…) under Global SEO → Canonical site URL.', 'global');
        } elseif (! str_starts_with($base, 'https://')) {
            $add('warn', 'Site URL is not HTTPS', $base.' — search engines prefer HTTPS. Install a certificate and use an https:// address.', 'global');
        } else {
            $add('ok', 'Canonical URL is set', 'Using '.$base.' for canonical links, sitemap and Open Graph URLs.', 'global');
        }

        $og = Seo::mediaUrl(Seo::setting('default_og_image'));
        $add($og ? 'ok' : 'bad', $og ? 'Default social sharing image is set' : 'Default social sharing image is missing', $og ? 'Shared links fall back to this 1200×630 image.' : 'Upload a 1200×630 image so shared links always show a preview.', 'global');

        $add(Seo::setting('google_site_verification') ? 'ok' : 'warn', Seo::setting('google_site_verification') ? 'Google Search Console verification added' : 'Google Search Console not verified', Seo::setting('google_site_verification') ? 'Remember to submit the sitemap in Search Console.' : 'Verify the site in Google Search Console and paste the code under Global SEO, then submit /sitemap.xml.', 'global');

        $add(preg_match('/^G-[A-Z0-9]{6,14}$/', trim(Seo::setting('ga4_id'))) ? 'ok' : 'info', preg_match('/^G-[A-Z0-9]{6,14}$/', trim(Seo::setting('ga4_id'))) ? 'Google Analytics is enabled' : 'Analytics is not configured', 'Optional. Add a GA4 measurement ID to see traffic and conversions (signed-in staff are never tracked).', 'global');

        // ---- Pages -----------------------------------------------------------------------------
        $titles = [];

        foreach (config('site.pages') as $key => $page) {
            $c = Seo::pageConfig($key);
            $full = Seo::title($c['title']);
            $titles[$key] = mb_strtolower($full);
            $tLen = mb_strlen($full);
            $dLen = mb_strlen((string) $c['description']);

            if ($c['noindex']) {
                $add('warn', $page['label'].' is set to noindex', 'This page is hidden from search engines. Untick “noindex” unless that is intentional.', 'pages');
            }

            if ($tLen > 65 || $tLen < 20) {
                $add('warn', $page['label'].': title length', "Title is {$tLen} characters (aim for 30–60). Search engines may truncate it.", 'pages');
            }

            if ($dLen < 70 || $dLen > 165) {
                $add('warn', $page['label'].': meta description length', "Description is {$dLen} characters (aim for 70–160).", 'pages');
            }
        }

        if (count($titles) !== count(array_unique($titles))) {
            $add('bad', 'Two pages share the same title', 'Every page should have a unique title. Review the Pages tab.', 'pages');
        }

        $pageIssues = array_filter($checks, fn ($c) => $c['tab'] === 'pages');

        if (! $pageIssues) {
            $add('ok', 'All page titles and descriptions look good', 'Lengths are within recommended ranges and titles are unique.', 'pages');
        }

        // ---- Catalogue -------------------------------------------------------------------------
        $products = Product::where('status', 'active')->get(['id', 'description', 'image_path', 'name', 'meta_description']);
        $thin = $products->filter(fn ($p) => mb_strlen((string) $p->description) < 50)->count();
        $noImage = $products->filter(fn ($p) => ! $p->imageUrl())->count();
        $noMeta = $products->filter(fn ($p) => blank($p->meta_description))->count();

        if ($products->isEmpty()) {
            $add('bad', 'No active products', 'Add products so the catalogue and product pages can rank.', 'catalogue');
        } else {
            $add($thin ? 'warn' : 'ok', $thin ? "{$thin} product(s) have a short or missing description" : 'All products have descriptions', $thin ? 'Write at least 50 characters (ideally 150+) of unique description for each product page.' : 'Good — descriptions give each product page unique content.', 'catalogue');
            $add($noImage ? 'warn' : 'ok', $noImage ? "{$noImage} product(s) have no photo" : 'All products have photos', $noImage ? 'Products without images get weak social previews and no image search presence. Upload a photo on the Product form.' : 'Photos are used for Open Graph images and the image sitemap.', 'catalogue');
            $add($noMeta ? 'info' : 'ok', $noMeta ? "{$noMeta} product(s) use an automatic meta description" : 'Every product has a custom meta description', 'Automatic descriptions are generated from the product text; write custom ones for your best sellers.', 'catalogue');
        }

        $catsMissing = ProductCategory::where('status', 'active')->get()->filter(fn ($c) => blank($c->seo_text) && blank($c->description))->count();
        $add($catsMissing ? 'warn' : 'ok', $catsMissing ? "{$catsMissing} category page(s) have no introduction text" : 'Category pages have introduction text', 'Category pages rank better with a paragraph describing the range.', 'catalogue');

        // ---- Content & trust -------------------------------------------------------------------
        $faqs = Faq::published()->count();
        $add($faqs >= 5 ? 'ok' : 'warn', $faqs >= 5 ? "{$faqs} FAQs published" : "Only {$faqs} FAQ(s) published", 'FAQs answer buyer questions and can appear as rich results. Aim for at least 5.', 'faqs');

        $socials = count(array_filter(array_map(fn ($k) => Seo::setting($k), ['social_facebook', 'social_x', 'social_instagram', 'social_linkedin', 'social_youtube'])));
        $add($socials ? 'ok' : 'warn', $socials ? "{$socials} social profile(s) linked" : 'No social profiles linked', 'Social links appear in the footer and in Organization structured data.', 'global');

        $nap = count(array_filter([Setting::get('company_phone'), Setting::get('company_email'), Setting::get('company_address')]));
        $add($nap === 3 ? 'ok' : 'warn', $nap === 3 ? 'Business contact details complete' : 'Business contact details incomplete', 'Add phone, email and address in Administration → Settings. They feed the footer and structured data (trust and local SEO).', 'overview');

        $urls = count(config('site.pages')) + ProductCategory::where('status', 'active')->count() + $products->count();
        $add('ok', 'Sitemap and robots.txt are generated automatically', "{$urls} URLs are listed in /sitemap.xml and the sitemap is referenced from /robots.txt.", 'overview');

        $counts = ['ok' => 0, 'warn' => 0, 'bad' => 0, 'info' => 0];

        foreach ($checks as $c) {
            $counts[$c['status']]++;
        }

        $scored = $counts['ok'] + $counts['warn'] + $counts['bad'];
        $score = $scored ? (int) round(max(0, ($counts['ok'] + $counts['warn'] * 0.4) / $scored * 100)) : 0;

        // Surface the worst problems first.
        usort($checks, fn ($a, $b) => ['bad' => 0, 'warn' => 1, 'info' => 2, 'ok' => 3][$a['status']] <=> ['bad' => 0, 'warn' => 1, 'info' => 2, 'ok' => 3][$b['status']]);

        return ['score' => $score, 'checks' => $checks, 'counts' => $counts, 'urls' => $urls];
    }
}
