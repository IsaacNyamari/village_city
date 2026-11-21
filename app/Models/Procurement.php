<?php

namespace App\Models;

use App\Enums\ProcurementStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Procurement extends Model
{
    use Auditable, HasReference;

    public const REF_PREFIX = 'PRC';

    public const REF_COLUMN = 'reference';

    protected $guarded = [];

    protected $casts = [
        'status' => ProcurementStatus::class,
        'procurement_date' => 'date',
        'received_at' => 'datetime',
        'inspected_at' => 'datetime',
    ];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function centre(): BelongsTo
    {
        return $this->belongsTo(CollectionCentre::class, 'collection_centre_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcurementItem::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(QualityInspection::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(SupplierSettlement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sourceName(): string
    {
        return $this->farmer?->name ?? $this->supplier?->business_name ?? 'Unassigned source';
    }

    public function sourceType(): string
    {
        return $this->farmer_id ? 'Farmer' : ($this->supplier_id ? 'Supplier' : '—');
    }

    /** Value of produce as ordered (before inspection determines the payable amount). */
    public function orderedValue(): float
    {
        return (float) $this->items->sum(fn ($i) => $i->quantity * $i->unit_price);
    }

    public function balance(): float
    {
        return round((float) $this->total_amount - (float) $this->amount_paid, 2);
    }
}
