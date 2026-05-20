<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    protected $fillable = [
        'name', 'description', 'amount', 'frequency', 'due_date',
        'category', 'is_active', 'last_paid_at', 'user_id',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'due_date'     => 'date',
        'last_paid_at' => 'datetime',
        'is_active'    => 'boolean',
    ];

    public function user() { return $this->belongsTo(User::class); }

    public function status(): string
    {
        if (!$this->is_active) return 'inactive';
        $due = Carbon::parse($this->due_date);
        if ($due->isPast() && !$due->isToday()) return 'overdue';
        if ($due->isToday()) return 'due_today';
        if ($due->diffInDays(Carbon::today()) <= 7) return 'upcoming';
        return 'scheduled';
    }

    public function nextDueDate(): ?Carbon
    {
        $base = Carbon::parse($this->due_date);
        return match ($this->frequency) {
            'one_time'    => null,
            'daily'       => $base->addDay(),
            'weekly'      => $base->addWeek(),
            'bi_weekly'   => $base->addWeeks(2),
            'monthly'     => $base->addMonth(),
            'quarterly'   => $base->addMonths(3),
            'semi_annual' => $base->addMonths(6),
            'annual'      => $base->addYear(),
            default       => null,
        };
    }
}
