<?php

namespace App\Models;

use App\Models\Concerns\HasReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockBatch extends Model
{
    use HasReference;

    public const REF_PREFIX = 'BAT';

    public const REF_COLUMN = 'batch_number';

    protected $guarded = [];

    protected $casts = ['received_at' => 'date', 'expiry_date' => 'date'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function centre(): BelongsTo
    {
        return $this->belongsTo(CollectionCentre::class, 'collection_centre_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }
}
