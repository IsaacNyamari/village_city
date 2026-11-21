<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['insurance_expiry' => 'date', 'inspection_expiry' => 'date'];

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Transporter::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class);
    }
}
