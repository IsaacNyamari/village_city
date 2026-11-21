<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['is_published' => 'boolean', 'show_on_home' => 'boolean'];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->orderBy('sort_order')->orderBy('id');
    }
}
