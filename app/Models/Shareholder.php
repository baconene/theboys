<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shareholder extends Model
{
    protected $fillable = ['name', 'email', 'ownership_percentage', 'status', 'notes'];

    protected $casts = [
        'ownership_percentage' => 'decimal:2',
    ];

    public function royaltyRules(): HasMany
    {
        return $this->hasMany(RoyaltyRule::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /** Total ownership across all active members (0–100). */
    public static function totalOwnership(?int $excludeId = null): float
    {
        return (float) static::active()
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->sum('ownership_percentage');
    }
}
