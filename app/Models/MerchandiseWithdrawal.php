<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Retiro de mercadería a crédito otorgado a un empleado.
 *
 * Ciclo de vida: pending → approve() → approved → [todas las cuotas pagadas] → paid
 *               pending/approved → cancel() → cancelled
 *
 * Múltiples retiros activos por empleado están permitidos.
 */
class MerchandiseWithdrawal extends Model
{
    protected $fillable = [
        'employee_id',
        'total_amount',
        'installments_count',
        'installment_amount',
        'outstanding_balance',
        'status',
        'notes',
        'approved_at',
        'approved_by_id',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'installment_amount' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'approved_at' => 'date',
        ];
    }

    // =========================================================================
    // RELACIONES
    // =========================================================================

    /** Empleado al que se otorgó el retiro. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Usuario que aprobó el retiro. */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /** Ítems (productos) del retiro. */
    public function items(): HasMany
    {
        return $this->hasMany(MerchandiseWithdrawalItem::class)->orderBy('id');
    }

    /** Cuotas del retiro ordenadas por número. */
    public function installments(): HasMany
    {
        return $this->hasMany(MerchandiseWithdrawalInstallment::class)->orderBy('installment_number');
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
            'approved' => 'Aprobado',
            'paid' => 'Pagado',
            'cancelled' => 'Cancelado',
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
            'approved' => 'info',
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
            'approved' => 'heroicon-o-check-badge',
            'paid' => 'heroicon-o-check-circle',
            'cancelled' => 'heroicon-o-minus-circle',
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
            'approved' => 'Aprobado',
            'paid' => 'Pagado',
            'cancelled' => 'Cancelado',
        ];
    }

    // =========================================================================
    // VERIFICADORES DE ESTADO
    // =========================================================================

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    // =========================================================================
    // ATRIBUTOS COMPUTADOS
    // =========================================================================

    /** Cantidad de cuotas pagadas. */
    public function getPaidInstallmentsCountAttribute(): int
    {
        return $this->installments()->where('status', 'paid')->count();
    }

    /** Cantidad de cuotas pendientes. */
    public function getPendingInstallmentsCountAttribute(): int
    {
        return $this->installments()->where('status', 'pending')->count();
    }

    /** Descripción del progreso: "X/Y cuotas". */
    public function getProgressDescriptionAttribute(): string
    {
        return "{$this->paid_installments_count}/{$this->installments_count} cuotas";
    }

    /** Porcentaje de avance del pago (0–100). */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->installments_count === 0) {
            return 0;
        }

        return (int) round(($this->paid_installments_count / $this->installments_count) * 100);
    }

    /** Retorna el label del estado actual. */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabel($this->status);
    }

    // =========================================================================
    // ACCIONES PRINCIPALES
    // =========================================================================

    /**
     * Aprueba el retiro y genera el plan de cuotas.
     *
     * Valida:
     *  - Estado pending
     *  - Contrato activo del empleado
     *
     * @param  int  $approvedById  ID del usuario que aprueba.
     * @return array{success: bool, message: string}
     */
    public function approve(int $approvedById): array
    {
        if (! $this->isPending()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden aprobar retiros en estado Pendiente.',
            ];
        }

        if (! $this->employee->activeContract) {
            return [
                'success' => false,
                'message' => 'El empleado no tiene un contrato activo.',
            ];
        }

        if ((float) $this->total_amount <= 0) {
            return [
                'success' => false,
                'message' => 'El monto total del retiro debe ser mayor a cero. Agregue productos primero.',
            ];
        }

        $installmentAmount = round((float) $this->total_amount / $this->installments_count, 2);
        $startDate = now()->addDays($this->first_installment_days);

        DB::transaction(function () use ($approvedById, $installmentAmount, $startDate) {
            $this->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by_id' => $approvedById,
                'installment_amount' => $installmentAmount,
                'outstanding_balance' => $this->total_amount,
            ]);

            $this->generateInstallments($startDate, $installmentAmount);
        });

        $formattedDate = $startDate->format('d/m/Y');

        return [
            'success' => true,
            'message' => 'Retiro aprobado. Primera cuota de Gs. '.number_format($installmentAmount, 0, ',', '.')." programada para {$formattedDate}.",
        ];
    }

    /**
     * Cancela el retiro. Las cuotas pendientes se cancelan; las pagadas se conservan.
     *
     * @param  string|null  $reason  Motivo de cancelación.
     * @return array{success: bool, message: string}
     */
    public function cancel(?string $reason = null): array
    {
        if ($this->isPaid() || $this->isCancelled()) {
            return [
                'success' => false,
                'message' => 'No se puede cancelar un retiro en este estado.',
            ];
        }

        DB::transaction(function () use ($reason) {
            $this->installments()->where('status', 'pending')->update(['status' => 'cancelled']);

            $notes = $this->notes;
            if ($reason) {
                $notes = $notes ? "{$notes}\n\nCancelación: {$reason}" : "Cancelación: {$reason}";
            }

            $this->update([
                'status' => 'cancelled',
                'outstanding_balance' => 0,
                'notes' => $notes,
            ]);
        });

        return [
            'success' => true,
            'message' => 'El retiro ha sido cancelado.',
        ];
    }

    /**
     * Verifica si todas las cuotas están pagadas y cierra el retiro.
     *
     * Llamado por MerchandiseInstallmentCalculator::markInstallmentsAsPaid().
     */
    public function checkIfPaid(): void
    {
        if ($this->isApproved() && $this->pending_installments_count === 0) {
            $this->update([
                'status' => 'paid',
                'outstanding_balance' => 0,
            ]);
        }
    }

    // =========================================================================
    // MÉTODOS DE PERÍODO Y FECHAS
    // =========================================================================

    /**
     * Calcula la fecha de inicio de la primera cuota según el tipo de nómina del empleado.
     */
    protected function calculateNextPayrollDate(): Carbon
    {
        $now = Carbon::now();
        $payrollType = $this->employee->activeContract?->payroll_type ?? 'monthly';

        return match ($payrollType) {
            'weekly' => $this->getNextMonday($now),
            'biweekly' => $this->getNextBiweeklyDate($now),
            default => $now->copy()->addMonth()->endOfMonth(),
        };
    }

    /** Retorna el próximo lunes desde una fecha dada. */
    protected function getNextMonday(Carbon $from): Carbon
    {
        $next = $from->copy();

        return $next->isMonday()
            ? $next->addWeek()->startOfDay()
            : $next->next(Carbon::MONDAY)->startOfDay();
    }

    /** Retorna la próxima fecha quincenal (día 16 o 1° del mes siguiente). */
    protected function getNextBiweeklyDate(Carbon $from): Carbon
    {
        $next = $from->copy();

        return $next->day < 16
            ? $next->day(16)->startOfDay()
            : $next->addMonth()->startOfMonth();
    }

    /**
     * Genera el plan de cuotas en partes iguales.
     *
     * La última cuota absorbe diferencias de redondeo.
     *
     * @param  Carbon  $startDate  Fecha de vencimiento de la primera cuota.
     * @param  float  $installmentAmount  Cuota base calculada.
     */
    protected function generateInstallments(Carbon $startDate, float $installmentAmount): void
    {
        $this->installments()->delete();

        $n = (int) $this->installments_count;
        $total = (float) $this->total_amount;
        $dueDate = $startDate->copy();
        $paid = 0.0;

        for ($i = 1; $i <= $n; $i++) {
            $amount = ($i === $n)
                ? round($total - $paid, 2)  // Última cuota: absorbe redondeo
                : $installmentAmount;

            $paid += $amount;

            MerchandiseWithdrawalInstallment::create([
                'merchandise_withdrawal_id' => $this->id,
                'installment_number' => $i,
                'amount' => $amount,
                'due_date' => $dueDate->copy(),
                'status' => 'pending',
            ]);

            $dueDate->addMonth();
        }
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Filtra retiros aprobados (en curso de cobro).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeApproved($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Filtra retiros pendientes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopePending($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Cantidad de retiros pendientes de aprobación (para badge de navegación).
     */
    public static function getPendingCount(): int
    {
        return static::where('status', 'pending')->count();
    }
}
