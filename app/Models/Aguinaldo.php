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
        'status',
        'paid_at',
    ];

    protected $casts = [
        'total_earned'     => 'decimal:2',
        'months_worked'    => 'decimal:2',
        'aguinaldo_amount' => 'decimal:2',
        'generated_at'     => 'datetime',
        'paid_at'          => 'datetime',
    ];

    // =========================================================================
    // RELACIONES
    // =========================================================================

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

    // =========================================================================
    // HELPERS ESTÁTICOS PARA ESTADOS
    // =========================================================================

    public static function getStatusOptions(): array
    {
        return [
            'pending' => 'Pendiente',
            'paid'    => 'Pagado',
        ];
    }

    public static function getStatusLabel(string $status): string
    {
        return self::getStatusOptions()[$status] ?? $status;
    }

    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'paid'    => 'success',
            default   => 'gray',
        };
    }

    public static function getStatusIcon(string $status): string
    {
        return match ($status) {
            'pending' => 'heroicon-o-clock',
            'paid'    => 'heroicon-o-check-circle',
            default   => 'heroicon-o-question-mark-circle',
        };
    }

    // =========================================================================
    // VERIFICADORES DE ESTADO
    // =========================================================================

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    // =========================================================================
    // ACCIONES
    // =========================================================================

    public function markAsPaid(): void
    {
        $this->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function markAsPending(): void
    {
        $this->update([
            'status'  => 'pending',
            'paid_at' => null,
        ]);
    }

    // =========================================================================
    // ATRIBUTOS COMPUTADOS
    // =========================================================================

    public function getTitleAttribute(): string
    {
        return "Aguinaldo {$this->period?->year} - {$this->employee?->full_name}";
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabel($this->status);
    }

    // =========================================================================
    // FORMATEO DE MONEDA
    // =========================================================================

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
