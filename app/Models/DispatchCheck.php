<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchCheck extends Model
{
    public const ITEMS = [
        'orders_match' => 'Cargo matches what was ordered',
        'labelling_ok' => 'Every parcel is correctly labelled',
        'packaging_ok' => 'Packaging meets the commodity standard',
        'all_cargo_loaded' => 'All cargo is loaded and accounted for',
        'sop_followed' => 'SOPs were followed',
    ];

    protected $guarded = [];

    protected $casts = ['signed_off_at' => 'datetime'];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function passed(): bool
    {
        return $this->signed_off_at !== null && collect(array_keys(self::ITEMS))->every(fn ($k) => (bool) $this->{$k});
    }
}
