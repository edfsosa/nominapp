<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_id',
        'type',
        'perception_type',
        'deduction_type',
        'description',
        'amount',
        'is_manual_override',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_manual_override' => 'boolean',
        ];
    }

    /**
     * Relación con el modelo Payroll, un item pertenece a una nómina
     */
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    /** @param Builder<PayrollItem> $query */
    public function scopePerceptions(Builder $query): Builder
    {
        return $query->where('type', 'perception');
    }

    /** @param Builder<PayrollItem> $query */
    public function scopeDeductions(Builder $query): Builder
    {
        return $query->where('type', 'deduction');
    }

    /**
     * Formatea el monto del item en guaraníes paraguayos
     */
    public function getFormattedAmountAttribute(): string
    {
        return Payroll::formatCurrency($this->amount);
    }

    /**
     * Formatea el monto del item como percepción (con +)
     */
    public function getFormattedPerceptionAttribute(): string
    {
        return Payroll::formatCurrency($this->amount);
    }

    /**
     * Formatea el monto del item como deducción (con -)
     */
    public function getFormattedDeductionAttribute(): string
    {
        return '- '.Payroll::formatCurrency($this->amount);
    }
}
