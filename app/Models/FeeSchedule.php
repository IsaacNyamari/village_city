<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeSchedule extends Model
{
    public const PAYEES = ['inspector' => 'Quality inspector', 'transporter' => 'Transporter', 'farmer' => 'Farmer / seller', 'last_mile' => 'Last-mile rider', 'platform' => 'Platform'];

    public const BASIS = ['flat' => 'Flat amount', 'per_km' => 'Per km', 'per_kg' => 'Per kg', 'percent' => 'Percent'];

    protected $guarded = [];
}
