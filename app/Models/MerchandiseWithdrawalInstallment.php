<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuota de descuento en nómina para un retiro de mercadería.
 *
 * Estados: pending → paid / cancelled
 */
class MerchandiseWithdrawalInstallment extends Model
{
    protected $fillable = [
        'merchandise_withdrawal_id',
        'installment_number',
        'amount',
        'due_date',
        'status',
        'paid_at',
        'employee_deduction_id',
        'payroll_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    // =========================================================================
    // RELACIONES
    // =========================================================================

    /** Retiro al que pertenece esta cuota. */
    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(MerchandiseWithdrawal::class, 'merchandise_withdrawal_id');
    }

    /** Nómina en la que fue cobrada (null si aún no se procesó). */
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

    /** Indica si la cuota está vencida (pendiente y fecha pasada). */
    public function isOverdue(): bool
    {
        return $this->isPending() && $this->due_date->isPast();
    }

    // =========================================================================
    // ATRIBUTOS COMPUTADOS
    // =========================================================================

    /** Descripción corta: "Cuota X/Y". */
    public function getDescriptionAttribute(): string
    {
        return "Cuota {$this->installment_number}/{$this->withdrawal->installments_count}";
    }

    /** Retorna el label del estado actual. */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabel($this->status);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Filtra cuotas pendientes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopePending($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Filtra cuotas vencidas (pendientes con due_date pasada).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeOverdue($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'pending')->where('due_date', '<', now());
    }
}
