<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasReference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use Auditable, HasFactory, HasReference;

    public const REF_PREFIX = 'CUS';

    public const REF_COLUMN = 'number';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Unpaid value of invoices that are not void. */
    public function outstandingBalance(): float
    {
        return (float) $this->invoices()->where('status', '!=', 'void')->selectRaw('COALESCE(SUM(total - amount_paid), 0) as balance')->value('balance');
    }

    /** Exposure = open (not yet invoiced-and-paid) commitments, used for credit checks. */
    public function creditExposure(): float
    {
        return (float) $this->orders()
            ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Draft->value])
            ->selectRaw('COALESCE(SUM(total - amount_paid), 0) as exposure')->value('exposure');
    }
}
