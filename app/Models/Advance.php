<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adelanto de salario: retiro anticipado del sueldo del mes en curso.
 *
 * Ciclo de vida: pending → approved → paid
 *                        ↘ rejected
 *               pending/approved → cancelled
 *
 * A diferencia de los préstamos, no tiene cuotas. El vínculo con nómina
 * se almacena directamente aquí (employee_deduction_id, payroll_id).
 */
class Advance extends Model
{
    protected $fillable = [
        'employee_id',
        'amount',
        'status',
        'approved_by_id',
        'approved_at',
        'notes',
        'employee_deduction_id',
        'payroll_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    // =========================================================================
    // RELACIONES
    // =========================================================================

    /** Empleado al que pertenece el adelanto. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Usuario que aprobó el adelanto. */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /** Nómina en la que fue descontado el adelanto. */
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    /** Deducción puntual generada en nómina para descontar el adelanto. */
    public function employeeDeduction(): BelongsTo
    {
        return $this->belongsTo(EmployeeDeduction::class);
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
            'rejected' => 'Rechazado',
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
            'rejected' => 'danger',
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
            'approved' => 'heroicon-o-check',
            'paid' => 'heroicon-o-check-circle',
            'rejected' => 'heroicon-o-x-circle',
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
            'rejected' => 'Rechazado',
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

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    // =========================================================================
    // ATRIBUTOS COMPUTADOS
    // =========================================================================

    /**
     * Retorna el label del estado actual.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabel($this->status);
    }

    // =========================================================================
    // ACCIONES PRINCIPALES
    // =========================================================================

    /**
     * Aprueba el adelanto.
     *
     * Valida que el empleado tenga contrato activo y que no exista otro
     * adelanto aprobado o pendiente para el mismo empleado.
     *
     * @param  int  $approvedById  ID del usuario que aprueba.
     * @return array{success: bool, message: string}
     */
    public function approve(int $approvedById): array
    {
        if (! $this->isPending()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden aprobar adelantos en estado Pendiente.',
            ];
        }

        if (! $this->employee->activeContract) {
            return [
                'success' => false,
                'message' => 'El empleado no tiene un contrato activo.',
            ];
        }

        // Verificar que no exista nómina del período actual ya generada
        $currentPeriod = $this->getCurrentPeriod();
        if ($currentPeriod && $this->payrollExistsForPeriod($currentPeriod)) {
            return [
                'success' => false,
                'message' => 'No se puede aprobar el adelanto porque la nómina del período actual ya fue generada para este empleado.',
            ];
        }

        // Verificar límite de adelantos activos por período
        $maxPerPeriod = app(\App\Settings\PayrollSettings::class)->advance_max_per_period;
        if ($maxPerPeriod > 0) {
            $activeCount = static::where('employee_id', $this->employee_id)
                ->whereIn('status', ['pending', 'approved'])
                ->where('id', '!=', $this->id)
                ->count();

            if ($activeCount >= $maxPerPeriod) {
                return [
                    'success' => false,
                    'message' => "El empleado ya tiene {$activeCount} adelanto(s) activo(s), lo que alcanza el límite de {$maxPerPeriod} por período.",
                ];
            }
        }

        // Para empleados mensuales: verificar que el total de adelantos activos no supere el salario
        if ($this->employee->activeContract->salary_type === 'mensual') {
            $salary = (float) $this->employee->activeContract->salary;
            $activeTotal = (float) static::where('employee_id', $this->employee_id)
                ->whereIn('status', ['pending', 'approved'])
                ->where('id', '!=', $this->id)
                ->sum('amount');

            if (($activeTotal + (float) $this->amount) > $salary) {
                $formatted = number_format($salary - $activeTotal, 0, ',', '.');

                return [
                    'success' => false,
                    'message' => "El total de adelantos activos superaría el salario mensual del empleado. Monto disponible: Gs. {$formatted}.",
                ];
            }
        }

        $this->update([
            'status' => 'approved',
            'approved_by_id' => $approvedById,
            'approved_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'Adelanto aprobado. Se descontará en la próxima liquidación de nómina.',
        ];
    }

    /**
     * Rechaza el adelanto (solo desde estado pending).
     *
     * @param  string|null  $reason  Motivo del rechazo.
     * @return array{success: bool, message: string}
     */
    public function reject(?string $reason = null): array
    {
        if (! $this->isPending()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden rechazar adelantos en estado Pendiente.',
            ];
        }

        $notes = $this->notes;
        if ($reason) {
            $notes = $notes ? "{$notes}\n\nRechazo: {$reason}" : "Rechazo: {$reason}";
        }

        $this->update([
            'status' => 'rejected',
            'notes' => $notes,
        ]);

        return [
            'success' => true,
            'message' => 'Adelanto rechazado.',
        ];
    }

    /**
     * Cancela el adelanto (desde pending o approved).
     *
     * Un adelanto aprobado pero aún no descontado en nómina puede cancelarse.
     * Si ya tiene payroll_id, no se puede cancelar (ya fue procesado).
     *
     * @param  string|null  $reason  Motivo de la cancelación.
     * @return array{success: bool, message: string}
     */
    public function cancel(?string $reason = null): array
    {
        if ($this->isPaid() || $this->isRejected() || $this->isCancelled()) {
            return [
                'success' => false,
                'message' => 'No se puede cancelar un adelanto en este estado.',
            ];
        }

        if ($this->payroll_id) {
            return [
                'success' => false,
                'message' => 'No se puede cancelar un adelanto que ya fue procesado en nómina.',
            ];
        }

        $notes = $this->notes;
        if ($reason) {
            $notes = $notes ? "{$notes}\n\nCancelación: {$reason}" : "Cancelación: {$reason}";
        }

        $this->update([
            'status' => 'cancelled',
            'notes' => $notes,
        ]);

        return [
            'success' => true,
            'message' => 'Adelanto cancelado.',
        ];
    }

    /**
     * Marca el adelanto como pagado al ser procesado en nómina.
     *
     * Llamado por AdvanceCalculator::markAdvancesAsPaid().
     *
     * @param  int  $payrollId  ID de la nómina que procesó el descuento.
     */
    public function markAsPaid(int $payrollId): void
    {
        $this->update([
            'status' => 'paid',
            'payroll_id' => $payrollId,
        ]);
    }

    // =========================================================================
    // MÉTODOS DE PERÍODO
    // =========================================================================

    /**
     * Obtiene el período de nómina activo para el tipo de nómina del empleado.
     */
    protected function getCurrentPeriod(): ?PayrollPeriod
    {
        $payrollType = $this->employee->activeContract?->payroll_type;

        if (! $payrollType) {
            return null;
        }

        return PayrollPeriod::where('frequency', $payrollType)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();
    }

    /**
     * Verifica si ya existe nómina para el empleado en el período dado.
     */
    protected function payrollExistsForPeriod(PayrollPeriod $period): bool
    {
        return Payroll::where('employee_id', $this->employee_id)
            ->where('payroll_period_id', $period->id)
            ->exists();
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
     * Filtra adelantos pendientes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Filtra adelantos aprobados (listos para descontar en nómina).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Filtra adelantos de un empleado específico.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    // =========================================================================
    // MÉTODOS ESTÁTICOS DE CONSULTA
    // =========================================================================

    /**
     * Retorna el adelanto pendiente o aprobado de un empleado, si existe.
     */
    public static function getActiveForEmployee(int $employeeId): ?self
    {
        return static::where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved'])
            ->first();
    }

    /**
     * Cantidad de adelantos pendientes de aprobación (para badge de navegación).
     */
    public static function getPendingCount(): int
    {
        return static::where('status', 'pending')->count();
    }
}
