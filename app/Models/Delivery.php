<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    use Auditable, HasReference;

    public const REF_PREFIX = 'DLV';

    public const REF_COLUMN = 'reference';

    protected $guarded = [];

    protected $casts = [
        'status' => DeliveryStatus::class,
        'dispatched_at' => 'datetime',
        'expected_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class)->orderBy('id');
    }

    public function isLate(): bool
    {
        if (! $this->expected_at) {
            return false;
        }

        return $this->delivered_at ? $this->delivered_at->gt($this->expected_at) : ($this->expected_at->isPast() && in_array($this->status->value, DeliveryStatus::activeValues(), true));
    }
}
