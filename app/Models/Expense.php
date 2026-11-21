<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use Auditable, HasReference;

    public const REF_PREFIX = 'EXP';

    public const REF_COLUMN = 'reference';

    protected $guarded = [];

    protected $casts = ['expense_date' => 'date'];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
