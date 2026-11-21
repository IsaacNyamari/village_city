<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    public const TOPICS = [
        'general' => 'General enquiry',
        'wholesale' => 'Wholesale / institutional supply',
        'farmer' => 'I am a farmer / supplier',
        'partnership' => 'Partnership',
        'support' => 'Order support',
    ];

    protected $guarded = [];

    protected $casts = ['handled_at' => 'datetime'];
}
