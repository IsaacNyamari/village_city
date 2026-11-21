<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Editable website copy. Administrators override the defaults in config/site.php through
 * Administration → SEO & Site Content; blank overrides fall back to the default.
 */
class SiteContent
{
    public static function get(string $key): string
    {
        [$group, $field] = explode('.', $key, 2);

        $value = Setting::get('content.'.$key);

        return filled($value) ? (string) $value : (string) config("site.content.{$group}.fields.{$field}.default", '');
    }

    /** @return list<string> */
    public static function lines(string $key): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', self::get($key))), fn ($l) => $l !== ''));
    }

    /** @return list<array{0:string, 1:string}> "Title | text" lines */
    public static function pairs(string $key): array
    {
        return array_map(function ($line) {
            [$title, $text] = array_pad(explode('|', $line, 2), 2, '');

            return [trim($title), trim($text)];
        }, self::lines($key));
    }

    public static function enabled(string $key): bool
    {
        return in_array(self::get($key), ['1', 'true', 'on'], true);
    }

    /** Public URL of an image field (uploaded file or bundled default). */
    public static function image(string $key): ?string
    {
        return Seo::mediaUrl(self::get($key));
    }
}
