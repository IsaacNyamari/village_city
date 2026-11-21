<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    use Auditable, HasSlug;

    protected $guarded = [];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
