<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispatch extends Model
{
    use Auditable, HasReference;

    public const REF_PREFIX = 'DSP';

    public const REF_COLUMN = 'reference';

    protected $guarded = [];

    protected $casts = ['departure_at' => 'datetime', 'expected_arrival_at' => 'datetime'];

    public function centre(): BelongsTo
    {
        return $this->belongsTo(CollectionCentre::class, 'collection_centre_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Transporter::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'delivery_route_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
