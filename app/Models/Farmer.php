<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasReference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Farmer extends Model
{
    use Auditable, HasFactory, HasReference;

    public const REF_PREFIX = 'FRM';

    public const REF_COLUMN = 'number';

    protected $guarded = [];

    protected $casts = ['registered_at' => 'date'];

    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'cooperative_id');
    }

    public function centre(): BelongsTo
    {
        return $this->belongsTo(CollectionCentre::class, 'collection_centre_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function harvestSchedules(): HasMany
    {
        return $this->hasMany(HarvestSchedule::class);
    }

    public function procurements(): HasMany
    {
        return $this->hasMany(Procurement::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(SupplierSettlement::class);
    }
}
