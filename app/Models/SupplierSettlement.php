<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierSettlement extends Model
{
    use Auditable, HasReference;

    public const REF_PREFIX = 'SET';

    public const REF_COLUMN = 'reference';

    protected $guarded = [];

    protected $casts = ['paid_at' => 'date'];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function payeeName(): string
    {
        return $this->farmer?->name ?? $this->supplier?->business_name ?? '—';
    }
}
