<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Append-only cash ledger. Reversals are recorded as new opposite entries.
 */
class FinancialTransaction extends Model
{
    public const CATEGORIES = [
        'customer_payment' => 'Customer Payment',
        'customer_payment_reversal' => 'Customer Payment Reversal',
        'supplier_payment' => 'Farmer / Supplier Payment',
        'supplier_payment_reversal' => 'Supplier Payment Reversal',
        'operating_expense' => 'Operating Expense',
        'expense_reversal' => 'Expense Reversal',
    ];

    protected $guarded = [];

    protected $casts = ['transaction_date' => 'date'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Financial transactions are immutable. Record a reversal instead.'));
        static::deleting(fn () => throw new LogicException('Financial transactions cannot be deleted. Record a reversal instead.'));
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
