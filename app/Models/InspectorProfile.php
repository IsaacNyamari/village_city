<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectorProfile extends Model
{
    use Auditable;

    public const STATUSES = ['applied' => 'Applied', 'vetted' => 'Vetted', 'trained' => 'Trained', 'active' => 'Active', 'suspended' => 'Suspended'];

    protected $guarded = [];

    protected $casts = ['vetted_at' => 'date', 'trained_at' => 'date', 'sop_signed_at' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function centre(): BelongsTo
    {
        return $this->belongsTo(CollectionCentre::class, 'collection_centre_id');
    }
}
