<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class County extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['is_sourcing' => 'boolean'];

    public function subCounties(): HasMany
    {
        return $this->hasMany(SubCounty::class);
    }

    public function farmers(): HasMany
    {
        return $this->hasMany(Farmer::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function centres(): HasMany
    {
        return $this->hasMany(CollectionCentre::class);
    }
}
