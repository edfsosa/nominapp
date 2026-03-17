<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AguinaldoItem extends Model
{
    protected $fillable = [
        'aguinaldo_id',
        'month',
        'base_salary',
        'perceptions',
        'extra_hours',
        'total',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'perceptions' => 'decimal:2',
        'extra_hours' => 'decimal:2',
        'total'       => 'decimal:2',
    ];

    public function aguinaldo(): BelongsTo
    {
        return $this->belongsTo(Aguinaldo::class);
    }

    public function getFormattedBaseSalaryAttribute(): string
    {
        return Aguinaldo::formatCurrency($this->base_salary);
    }

    public function getFormattedPerceptionsAttribute(): string
    {
        return Aguinaldo::formatCurrency($this->perceptions);
    }

    public function getFormattedExtraHoursAttribute(): string
    {
        return Aguinaldo::formatCurrency($this->extra_hours);
    }

    public function getFormattedTotalAttribute(): string
    {
        return Aguinaldo::formatCurrency($this->total);
    }
}
