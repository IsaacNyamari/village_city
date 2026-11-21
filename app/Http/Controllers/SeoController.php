<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\Seo;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    /** Paths that must never be crawled (the operations app, auth and private uploads). */
    public const DISALLOW = [
        '/dashboard', '/login', '/register', '/forgot-password', '/reset-password', '/confirm-password', '/verify-email', '/logout', '/settings',
        '/help', '/marketplace', '/farmers', '/suppliers', '/cooperatives', '/centres', '/products', '/products-categories', '/procurement',
        '/inventory', '/quality-control', '/customers', '/orders', '/sales', '/invoices', '/payments', '/vehicles', '/transporters',
        '/dispatches', '/deliveries', '/routes', '/expenses', '/settlements', '/transactions', '/reports', '/analytics', '/forecasting',
        '/market-intelligence', '/users', '/roles', '/counties', '/system-settings', '/seo-content', '/audit-logs', '/inquiries',
        '/livewire', '/storage/inspections/', '/storage/proof-of-delivery/',
    ];

    public function robots(): Response
    {
        $lines = ['# robots.txt for '.Seo::siteName()];

        if (! Seo::indexable()) {
            $lines = [...$lines, 'User-agent: *', 'Disallow: /'];
        } else {
            $lines = [...$lines, 'User-agent: *', 'Allow: /'];

            foreach (self::DISALLOW as $path) {
                $lines[] = 'Disallow: '.$path;
            }

            // Internal search result pages add no value to the index.
            $lines[] = 'Disallow: /produce?*search=';

            foreach (preg_split('/\R/', Seo::setting('robots_extra')) as $extra) {
                if (trim($extra) !== '') {
                    $lines[] = trim($extra);
                }
            }

            $lines[] = '';
            $lines[] = 'Sitemap: '.Seo::url('sitemap.xml');
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function sitemap(): Response
    {
        $entries = [];

        foreach (config('site.pages') as $key => $page) {
            $config = Seo::pageConfig($key);

            if ($config['noindex'] || ($key === 'faq' && ! Faq::published()->exists())) {
                continue;
            }

            $entries[] = [
                'loc' => Seo::url($page['path']),
                'lastmod' => $this->lastModified($key),
                'changefreq' => $page['changefreq'],
                'priority' => $page['priority'],
                'images' => array_filter([Seo::mediaUrl($config['og_image'])]),
            ];
        }

        foreach (ProductCategory::where('status', 'active')->get() as $category) {
            $entries[] = [
                'loc' => Seo::url('produce/category/'.$category->slug),
                'lastmod' => $category->updated_at,
                'changefreq' => 'weekly', 'priority' => '0.8', 'images' => [],
            ];
        }

        foreach (Product::with('category')->where('status', 'active')->get() as $product) {
            $entries[] = [
                'loc' => Seo::url('produce/'.$product->slug),
                'lastmod' => $product->updated_at,
                'changefreq' => 'weekly', 'priority' => '0.7',
                'images' => array_filter([$product->imageUrl() ? Seo::mediaUrl($this->relative($product->imageUrl())) ?? $product->imageUrl() : null]),
                'image_title' => $product->name,
            ];
        }

        $xml = view('seo.sitemap', ['entries' => $entries])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    protected function lastModified(string $page): ?\Illuminate\Support\Carbon
    {
        $stamp = \App\Models\Setting::query()->where('key', 'like', "seo.page.{$page}.%")->orWhere('key', 'like', "content.{$page}.%")->max('updated_at');

        return $stamp ? \Illuminate\Support\Carbon::parse($stamp) : null;
    }

    /** Turn a full asset URL back into a path relative to the site root. */
    protected function relative(string $url): string
    {
        return ltrim((string) parse_url($url, PHP_URL_PATH), '/');
    }
}
