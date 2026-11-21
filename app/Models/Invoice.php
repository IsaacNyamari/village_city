<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use Auditable, HasReference;

    public const REF_PREFIX = 'INV';

    public const REF_COLUMN = 'number';

    protected $guarded = [];

    protected $casts = ['issue_date' => 'date', 'due_date' => 'date'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function balance(): float
    {
        return round((float) $this->total - (float) $this->amount_paid, 2);
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->status !== 'void' && $this->due_date !== null && $this->due_date->isPast() && $this->balance() > 0;
    }
}
