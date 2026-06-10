<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoyaltyRule extends Model
{
    protected $fillable = [
        'scope', 'product_id', 'category_id', 'recipient_name', 'shareholder_id',
        'royalty_percentage', 'effective_date', 'expiration_date', 'is_active',
    ];

    protected $casts = [
        'royalty_percentage' => 'decimal:2',
        'effective_date'     => 'date',
        'expiration_date'    => 'date',
        'is_active'          => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function shareholder(): BelongsTo
    {
        return $this->belongsTo(Shareholder::class);
    }

    /** Rules active and effective for any part of the given period. */
    public function scopeEffectiveDuring($query, string $start, string $end)
    {
        return $query->where('is_active', true)
            ->whereDate('effective_date', '<=', $end)
            ->where(function ($q) use ($start) {
                $q->whereNull('expiration_date')->orWhereDate('expiration_date', '>=', $start);
            });
    }
}
