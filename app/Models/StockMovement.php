<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Permanent, append-only stock ledger. Corrections are made with new movements.
 */
class StockMovement extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Stock movements are immutable. Record an adjustment instead.'));
        static::deleting(fn () => throw new LogicException('Stock movements cannot be deleted. Record an adjustment instead.'));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function centre(): BelongsTo
    {
        return $this->belongsTo(CollectionCentre::class, 'collection_centre_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'stock_batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
