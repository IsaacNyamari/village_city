<?php

namespace App\Models\Concerns;

/**
 * Generates a sequential human-readable reference (e.g. FRM-00012) after creation.
 * Models define: const REF_PREFIX and const REF_COLUMN.
 */
trait HasReference
{
    public static function bootHasReference(): void
    {
        static::created(function ($model) {
            $column = static::REF_COLUMN;

            if (blank($model->{$column})) {
                $model->{$column} = static::REF_PREFIX.'-'.str_pad((string) $model->getKey(), 5, '0', STR_PAD_LEFT);
                $model->saveQuietly();
            }
        });
    }
}
