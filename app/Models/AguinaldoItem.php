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
        'total' => 'decimal:2',
    ];

    public function aguinaldo(): BelongsTo
    {
        return $this->belongsTo(Aguinaldo::class);
    }

    /**
     * Formatea un monto en guaraníes paraguayos
     */
    public static function formatCurrency(float|int|null $amount): string
    {
        if ($amount === null) {
            return 'Gs. 0';
        }

        return 'Gs. ' . number_format($amount, 0, ',', '.');
    }

    public function getFormattedBaseSalaryAttribute(): string
    {
        return self::formatCurrency($this->base_salary);
    }

    public function getFormattedPerceptionsAttribute(): string
    {
        return self::formatCurrency($this->perceptions);
    }

    public function getFormattedExtraHoursAttribute(): string
    {
        return self::formatCurrency($this->extra_hours);
    }

    public function getFormattedTotalAttribute(): string
    {
        return self::formatCurrency($this->total);
    }
}
