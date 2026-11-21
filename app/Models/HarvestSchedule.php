<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HarvestSchedule extends Model
{
    protected $guarded = [];

    protected $casts = ['expected_harvest_date' => 'date'];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
