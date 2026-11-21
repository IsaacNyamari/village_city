<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityInspection extends Model
{
    protected $guarded = [];

    protected $casts = ['inspected_at' => 'datetime'];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProcurementItem::class, 'procurement_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }
}
