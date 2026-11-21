<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasReference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use Auditable, HasFactory, HasReference;

    public const REF_PREFIX = 'SUP';

    public const REF_COLUMN = 'number';

    protected $guarded = [];

    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function procurements(): HasMany
    {
        return $this->hasMany(Procurement::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Farmer::class, 'cooperative_id');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(SupplierSettlement::class);
    }

    public function getNameAttribute(): string
    {
        return (string) $this->business_name;
    }
}
