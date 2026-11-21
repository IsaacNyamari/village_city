<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\HasReference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use Auditable, HasFactory, HasReference, HasSlug;

    public const REF_PREFIX = 'PRD';

    public const REF_COLUMN = 'code';

    protected $guarded = [];

    protected $casts = ['is_perishable' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function procurementItems(): HasMany
    {
        return $this->hasMany(ProcurementItem::class);
    }

    public function farmers(): BelongsToMany
    {
        return $this->belongsToMany(Farmer::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class);
    }

    /** Saleable stock on hand across all locations. */
    public function stockOnHand(): float
    {
        return (float) $this->batches()->sum('quantity');
    }

    /** Uploaded image first; otherwise a bundled catalogue photo at public/images/products/<slug>.jpg. */
    public function imageUrl(): ?string
    {
        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }

        $relative = 'images/products/'.\Illuminate\Support\Str::slug($this->name).'.jpg';

        return file_exists(public_path($relative)) ? asset($relative) : null;
    }
}
