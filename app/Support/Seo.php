<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds the SEO data (title, description, canonical, robots, Open Graph, Twitter, JSON-LD) for a page.
 * Global values and per-page overrides are managed in Administration → SEO & Site Content.
 */
class Seo
{
    /** @var array<string, array{0:int,1:int}|null> */
    protected static array $sizes = [];

    // ---- Settings -----------------------------------------------------------------------------------

    /** Global SEO value (saved override, else config default). */
    public static function setting(string $key): string
    {
        $value = Setting::get('seo.'.$key);

        return filled($value) ? (string) $value : (string) config("site.global.{$key}.default", '');
    }

    public static function pageSetting(string $page, string $field): ?string
    {
        $value = Setting::get("seo.page.{$page}.{$field}");

        return filled($value) ? (string) $value : null;
    }

    public static function siteName(): string
    {
        return self::setting('site_name') ?: config('app.name', 'Village City');
    }

    public static function indexable(): bool
    {
        return in_array(Setting::get('seo.allow_indexing', config('site.global.allow_indexing.default')), ['1', 'true', 'on', 1, true], true);
    }

    // ---- URLs ---------------------------------------------------------------------------------------

    public static function baseUrl(): string
    {
        $base = trim(self::setting('canonical_base')) ?: (string) config('app.url');

        return rtrim($base, '/');
    }

    public static function url(string $path = '/'): string
    {
        return $path === '/' || $path === '' ? self::baseUrl().'/' : self::baseUrl().'/'.ltrim($path, '/');
    }

    /** Public URL for a stored media path: bundled (public/…) or uploaded (public disk). Absolute. */
    public static function mediaUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $path = ltrim($path, '/');

        if (is_file(public_path($path))) {
            return self::url($path);
        }

        if (Storage::disk('public')->exists($path)) {
            return self::url('storage/'.$path);
        }

        return null;
    }

    /** @return array{0:int,1:int}|null */
    public static function imageSize(?string $path): ?array
    {
        if (blank($path) || preg_match('#^https?://#i', (string) $path)) {
            return null;
        }

        $path = ltrim($path, '/');

        return self::$sizes[$path] ??= (function () use ($path) {
            $file = is_file(public_path($path)) ? public_path($path) : (Storage::disk('public')->exists($path) ? Storage::disk('public')->path($path) : null);
            $info = $file ? @getimagesize($file) : false;

            return $info ? [$info[0], $info[1]] : null;
        })();
    }

    // ---- Text helpers -------------------------------------------------------------------------------

    /** Complete a page title with the brand using the configured template. */
    public static function title(string $title): string
    {
        $name = self::siteName();

        if (Str::contains(Str::lower($title), Str::lower($name))) {
            return $title;
        }

        $template = self::setting('title_template') ?: '%s | '.$name;

        return str_contains($template, '%s') ? sprintf($template, $title) : $title.' | '.$name;
    }

    /** Plain-text excerpt suitable for a meta description. */
    public static function excerpt(?string $text, int $max = 158): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));

        // Total length (including the ellipsis) never exceeds $max.
        return Str::limit($text, $max - 1, '…');
    }

    // ---- Page data ----------------------------------------------------------------------------------

    /** Effective SEO config for one of the known public pages (defaults + admin overrides). */
    public static function pageConfig(string $page): array
    {
        $defaults = config("site.pages.{$page}", []);

        return [
            'title' => self::pageSetting($page, 'title') ?? ($defaults['title'] ?? self::siteName()),
            'description' => self::pageSetting($page, 'description') ?? ($defaults['description'] ?? self::setting('default_description')),
            'og_title' => self::pageSetting($page, 'og_title'),
            'og_description' => self::pageSetting($page, 'og_description'),
            'og_image' => self::pageSetting($page, 'og_image') ?? ($defaults['og'] ?? null),
            'noindex' => in_array(self::pageSetting($page, 'noindex'), ['1', 'true', 'on'], true),
            'path' => $defaults['path'] ?? '/',
            'schema' => $defaults['schema'] ?? 'WebPage',
        ];
    }

    /**
     * SEO data for a known public page.
     *
     * @param  array  $extra  keys passed to build(): path, schema (extra JSON-LD nodes), breadcrumbs, page_number …
     */
    public static function page(string $page, array $extra = []): array
    {
        $c = self::pageConfig($page);
        $extra['schema_type'] ??= $c['schema'];

        // Caller-supplied values (e.g. a paginated title or noindex for search results) win over the defaults.
        return self::build($extra + [
            'title' => $c['title'],
            'description' => $c['description'],
            'og_title' => $c['og_title'],
            'og_description' => $c['og_description'],
            'image' => $c['og_image'],
            'path' => $c['path'],
            'noindex' => $c['noindex'],
            'page_key' => $page,
        ]);
    }

    /**
     * Assemble the full SEO payload.
     *
     * @param  array{title:string, description?:?string, path?:string, image?:?string, image_alt?:?string, og_title?:?string, og_description?:?string, og_type?:string, noindex?:bool, schema?:list<array>, schema_type?:string, breadcrumbs?:list<array{0:string,1:?string}>, product?:array, canonical?:?string, preload?:?string, page_key?:string}  $d
     */
    public static function build(array $d): array
    {
        $path = $d['path'] ?? '/'.ltrim(request()->path(), '/');
        $canonical = $d['canonical'] ?? self::url($path);
        $title = self::title($d['title']);
        $description = self::excerpt($d['description'] ?? self::setting('default_description'), 320);
        $noindex = ! empty($d['noindex']) || ! self::indexable();

        $image = self::mediaUrl($d['image'] ?? null)
            ?? self::mediaUrl(self::setting('default_og_image'))
            ?? self::mediaUrl('images/og/default.jpg');
        $imagePath = $d['image'] ?? self::setting('default_og_image');
        $size = self::imageSize($imagePath) ?? self::imageSize('images/og/default.jpg');
        $imageAlt = $d['image_alt'] ?? ($d['og_title'] ?? $d['title']);

        $ogTitle = $d['og_title'] ?? $d['title'];
        $ogDescription = $d['og_description'] ?? $description;
        $handle = ltrim(self::setting('twitter_handle'), '@');

        $schema = array_filter([
            Schema::organization(),
            Schema::website(),
            Schema::webPage($d['schema_type'] ?? 'WebPage', $canonical, $d['title'], $description, $image),
            ! empty($d['breadcrumbs']) ? Schema::breadcrumbs($d['breadcrumbs']) : null,
            ...($d['schema'] ?? []),
        ]);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $noindex
                ? 'noindex, follow'
                : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'locale' => str_replace('-', '_', self::setting('locale') ?: 'en_KE'),
            'site_name' => self::siteName(),
            'og' => [
                'type' => $d['og_type'] ?? 'website',
                'title' => $ogTitle,
                'description' => self::excerpt($ogDescription, 200),
                'url' => $canonical,
                'image' => $image,
                'image_width' => $size[0] ?? null,
                'image_height' => $size[1] ?? null,
                'image_alt' => $imageAlt,
                'product' => $d['product'] ?? null,
            ],
            'twitter' => [
                'card' => 'summary_large_image',
                'site' => $handle ? '@'.$handle : null,
                'title' => $ogTitle,
                'description' => self::excerpt($ogDescription, 200),
                'image' => $image,
                'image_alt' => $imageAlt,
            ],
            'verification' => [
                'google' => self::setting('google_site_verification') ?: null,
                'bing' => self::setting('bing_site_verification') ?: null,
            ],
            'analytics' => preg_match('/^G-[A-Z0-9]{6,14}$/', trim(self::setting('ga4_id'))) ? trim(self::setting('ga4_id')) : null,
            'preload' => $d['preload'] ?? null,
            'schema' => array_values($schema),
        ];
    }

    /** Minimal head data for non-public pages (app, auth): never indexed. */
    public static function privatePage(string $title): array
    {
        return [
            'title' => $title.' · '.self::siteName(),
            'robots' => 'noindex, nofollow, noarchive',
        ];
    }
}
