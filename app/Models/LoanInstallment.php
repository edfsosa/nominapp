<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuota individual de un préstamo (amortización francesa).
 *
 * Cada cuota almacena el monto total (amount), la porción capital (capital_amount)
 * y la porción interés (interest_amount). Con tasa 0%: capital = amount, interés = 0.
 */
class LoanInstallment extends Model
{
    protected $fillable = [
        'loan_id',
        'installment_number',
        'amount',
        'capital_amount',
        'interest_amount',
        'due_date',
        'status',
        'paid_at',
        'notes',
        'employee_deduction_id',
        'payroll_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'capital_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    // =========================================================================
    // RELACIONES
    // =========================================================================

    /** Préstamo al que pertenece esta cuota. */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /** Nómina en la que fue cobrada esta cuota (null si aún no se procesó). */
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    // =========================================================================
    // HELPERS ESTÁTICOS — ESTADOS
    // =========================================================================

    /**
     * Retorna el label legible de un estado.
     */
    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'paid' => 'Pagada',
            'cancelled' => 'Cancelada',
            default => 'Desconocido',
        };
    }

    /**
     * Retorna el color semántico Filament para un estado.
     */
    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'paid' => 'success',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Retorna el icono heroicon para un estado.
     */
    public static function getStatusIcon(string $status): string
    {
        return match ($status) {
            'pending' => 'heroicon-o-clock',
            'paid' => 'heroicon-o-check-circle',
            'cancelled' => 'heroicon-o-x-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Retorna las opciones de estado para selects Filament.
     *
     * @return array<string, string>
     */
    public static function getStatusOptions(): array
    {
        return [
            'pending' => 'Pendiente',
            'paid' => 'Pagada',
            'cancelled' => 'Cancelada',
        ];
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

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Indica si la cuota está vencida (pendiente y fecha pasada).
     */
    public function isOverdue(): bool
    {
        return $this->isPending() && $this->due_date->isPast();
    }

    // =========================================================================
    // ATRIBUTOS COMPUTADOS
    // =========================================================================

    /**
     * Descripción corta: "Cuota X/Y".
     */
    public function getDescriptionAttribute(): string
    {
        return "Cuota {$this->installment_number}/{$this->loan->installments_count}";
    }

    /**
     * Descripción completa con desglose capital/interés cuando hay interés.
     *
     * Ejemplo con interés:  "Préstamo - Cuota 3/12 | Capital: Gs. 80.000 | Interés: Gs. 5.000"
     * Ejemplo sin interés:  "Préstamo - Cuota 3/12"
     */
    public function getFullDescriptionAttribute(): string
    {
        $base = "Préstamo - Cuota {$this->installment_number}/{$this->loan->installments_count}";

        if ((float) $this->interest_amount > 0) {
            $capital = number_format((float) $this->capital_amount, 0, ',', '.');
            $interest = number_format((float) $this->interest_amount, 0, ',', '.');
            $base .= " | Capital: Gs. {$capital} | Interés: Gs. {$interest}";
        }

        return $base;
    }

    /**
     * Retorna el label del estado actual.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabel($this->status);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Filtra por estado.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Filtra cuotas pendientes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Filtra cuotas pagadas.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Filtra cuotas vencidas (pendientes con due_date pasada).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')->where('due_date', '<', now());
    }

    /**
     * Filtra cuotas del mes actual.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCurrentMonth($query)
    {
        return $query->whereMonth('due_date', now()->month)
            ->whereYear('due_date', now()->year);
    }

    /**
     * Filtra cuotas con due_date dentro de un rango de fechas.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $startDate
     * @param  mixed  $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('due_date', [$startDate, $endDate]);
    }

    /**
     * Filtra cuotas de préstamos activos.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForActiveLoan($query)
    {
        return $query->whereHas('loan', fn ($q) => $q->where('status', 'approved'));
    }
}
