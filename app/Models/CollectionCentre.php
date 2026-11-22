<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasReference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionCentre extends Model
{
    use Auditable, HasFactory, HasReference;

    public const REF_PREFIX = 'CTR';

    public const REF_COLUMN = 'code';

    protected $guarded = [];

    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function officers(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function farmers(): HasMany
    {
        return $this->hasMany(Farmer::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function procurements(): HasMany
    {
        return $this->hasMany(Procurement::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(DailyListing::class);
    }

    /** Market days are stored as text such as "Mon, Thu"; matched on the first three letters. Blank = trades daily. */
    public function isMarketDay(?\Illuminate\Support\Carbon $date = null): bool
    {
        $day = strtolower(($date ?? today())->format('D'));

        return blank($this->market_days) || str_contains(strtolower($this->market_days), $day);
    }
}
