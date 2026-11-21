<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Keeps a unique, URL-safe `slug` column. Generated from `name` when blank; an explicit slug is
 * normalised. Existing slugs are never regenerated when the name changes, so public URLs stay stable.
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::saving(function ($model) {
            $slug = Str::slug((string) $model->slug);

            if ($slug === '') {
                $slug = Str::slug((string) $model->name) ?: Str::lower(class_basename($model)).'-'.Str::random(5);
            }

            $model->slug = static::uniqueSlug($slug, $model->getKey());
        });
    }

    protected static function uniqueSlug(string $slug, mixed $ignoreId = null): string
    {
        $candidate = $slug;
        $i = 2;

        while (static::query()->where('slug', $candidate)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $candidate = $slug.'-'.$i++;
        }

        return $candidate;
    }
}
