<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryRoute extends Model
{
    use Auditable;

    protected $guarded = [];

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class);
    }
}
