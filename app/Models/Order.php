<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use Auditable, HasReference;

    public const REF_PREFIX = 'ORD';

    public const REF_COLUMN = 'reference';

    protected $guarded = [];

    protected $casts = [
        'status' => OrderStatus::class,
        'expected_delivery_date' => 'date',
        'confirmed_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->where('status', '!=', 'void')->latest('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function procurements(): HasMany
    {
        return $this->hasMany(Procurement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function balance(): float
    {
        return round((float) $this->total - (float) $this->amount_paid, 2);
    }
}
