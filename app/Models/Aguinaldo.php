<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aguinaldo extends Model
{
    protected $fillable = [
        'aguinaldo_period_id',
        'employee_id',
        'total_earned',
        'months_worked',
        'aguinaldo_amount',
        'pdf_path',
        'generated_at',
    ];

    protected $casts = [
        'total_earned' => 'decimal:2',
        'months_worked' => 'decimal:2',
        'aguinaldo_amount' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(AguinaldoPeriod::class, 'aguinaldo_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AguinaldoItem::class);
    }

    public function getTitleAttribute(): string
    {
        return "Aguinaldo {$this->period?->year} - {$this->employee?->full_name}";
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

    public function getFormattedTotalEarnedAttribute(): string
    {
        return self::formatCurrency($this->total_earned);
    }

    public function getFormattedAguinaldoAmountAttribute(): string
    {
        return self::formatCurrency($this->aguinaldo_amount);
    }
}
