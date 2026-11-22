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

    public const CHECKLIST = [
        'valid_insurance' => 'Valid insurance',
        'ntsa_inspection' => 'Valid NTSA inspection sticker',
        'roadworthy' => 'Roadworthy (tyres, brakes, lights)',
        'clean_cargo_area' => 'Clean, dry, odour-free cargo area',
        'covered_load' => 'Covered: protects produce from sun and rain',
        'cooling_if_needed' => 'Cooling available for perishables',
        'driver_licensed' => 'Driver licensed and vetted',
        'driver_reachable' => 'Driver reachable / trackable by phone',
    ];

    public const CERT_STATUS = ['pending' => 'Pending', 'certified' => 'Certified', 'rejected' => 'Rejected', 'suspended' => 'Suspended'];

    protected $casts = ['insurance_expiry' => 'date', 'inspection_expiry' => 'date', 'certified_at' => 'date', 'certification_expiry' => 'date', 'certification_checklist' => 'array'];

    public function isCertified(): bool
    {
        return $this->certification_status === 'certified'
            && (! $this->certification_expiry || ! $this->certification_expiry->isPast());
    }

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Transporter::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class);
    }
}
