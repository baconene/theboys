<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DistributionSnapshot extends Model
{
    protected $fillable = [
        'period_start', 'period_end', 'distribution_basis',
        'gross_amount', 'refunds_amount', 'cogs_amount', 'expenses_amount',
        'royalty_amount', 'distributable_amount', 'members_amount', 'company_amount',
        'filters_applied', 'created_by',
    ];

    protected $casts = [
        'period_start'         => 'date',
        'period_end'           => 'date',
        'filters_applied'      => 'array',
        'gross_amount'         => 'decimal:2',
        'refunds_amount'       => 'decimal:2',
        'cogs_amount'          => 'decimal:2',
        'expenses_amount'      => 'decimal:2',
        'royalty_amount'       => 'decimal:2',
        'distributable_amount' => 'decimal:2',
        'members_amount'       => 'decimal:2',
        'company_amount'       => 'decimal:2',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(DistributionSnapshotDetail::class, 'snapshot_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
