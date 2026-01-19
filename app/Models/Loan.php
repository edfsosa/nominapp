<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Loan extends Model
{
    protected $fillable = [
        'employee_id',
        'type',
        'amount',
        'installments_count',
        'installment_amount',
        'status',
        'reason',
        'granted_at',
        'granted_by_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'granted_at' => 'date',
    ];

    /**
     * Relación con el empleado
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Usuario que otorgó el préstamo
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_id');
    }

    /**
     * Cuotas del préstamo
     */
    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }

    /**
     * Obtiene el label traducido del tipo
     */
    public static function getTypeLabel(string $type): string
    {
        return match ($type) {
            'loan' => 'Préstamo',
            'advance' => 'Adelanto',
            default => 'Desconocido',
        };
    }

    /**
     * Obtiene el color del badge según el tipo
     */
    public static function getTypeColor(string $type): string
    {
        return match ($type) {
            'loan' => 'info',
            'advance' => 'warning',
            default => 'gray',
        };
    }

    /**
     * Obtiene el icono según el tipo
     */
    public static function getTypeIcon(string $type): string
    {
        return match ($type) {
            'loan' => 'heroicon-o-banknotes',
            'advance' => 'heroicon-o-clock',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Obtiene todas las opciones de tipo para selects
     */
    public static function getTypeOptions(): array
    {
        return [
            'loan' => 'Préstamo',
            'advance' => 'Adelanto de Salario',
        ];
    }

    /**
     * Obtiene el label traducido del estado
     */
    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'active' => 'Activo',
            'paid' => 'Pagado',
            'cancelled' => 'Cancelado',
            'defaulted' => 'Incobrable',
            default => 'Desconocido',
        };
    }

    /**
     * Obtiene el color del badge según el estado
     */
    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'active' => 'info',
            'paid' => 'success',
            'cancelled' => 'gray',
            'defaulted' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Obtiene el icono según el estado
     */
    public static function getStatusIcon(string $status): string
    {
        return match ($status) {
            'pending' => 'heroicon-o-clock',
            'active' => 'heroicon-o-play',
            'paid' => 'heroicon-o-check-circle',
            'cancelled' => 'heroicon-o-x-circle',
            'defaulted' => 'heroicon-o-exclamation-triangle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Obtiene todas las opciones de estado para selects
     */
    public static function getStatusOptions(): array
    {
        return [
            'pending' => 'Pendiente',
            'active' => 'Activo',
            'paid' => 'Pagado',
            'cancelled' => 'Cancelado',
            'defaulted' => 'Incobrable',
        ];
    }

    /**
     * Verifica si el préstamo está pendiente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Verifica si el préstamo está activo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verifica si el préstamo está pagado
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Verifica si el préstamo fue cancelado
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Verifica si el préstamo es incobrable
     */
    public function isDefaulted(): bool
    {
        return $this->status === 'defaulted';
    }

    /**
     * Verifica si es un préstamo
     */
    public function isLoan(): bool
    {
        return $this->type === 'loan';
    }

    /**
     * Verifica si es un adelanto
     */
    public function isAdvance(): bool
    {
        return $this->type === 'advance';
    }

    /**
     * Obtiene el monto total pagado
     */
    public function getPaidAmountAttribute(): float
    {
        return (float) $this->installments()->where('status', 'paid')->sum('amount');
    }

    /**
     * Obtiene el monto pendiente
     */
    public function getPendingAmountAttribute(): float
    {
        return (float) $this->installments()->where('status', 'pending')->sum('amount');
    }

    /**
     * Obtiene el número de cuotas pagadas
     */
    public function getPaidInstallmentsCountAttribute(): int
    {
        return $this->installments()->where('status', 'paid')->count();
    }

    /**
     * Obtiene el número de cuotas pendientes
     */
    public function getPendingInstallmentsCountAttribute(): int
    {
        return $this->installments()->where('status', 'pending')->count();
    }

    /**
     * Obtiene una descripción del progreso
     */
    public function getProgressDescriptionAttribute(): string
    {
        return "{$this->paid_installments_count}/{$this->installments_count} cuotas";
    }

    /**
     * Obtiene el porcentaje de progreso
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->installments_count === 0) {
            return 0;
        }

        return (int) round(($this->paid_installments_count / $this->installments_count) * 100);
    }

    /**
     * Activa el préstamo y genera las cuotas
     *
     * @param int $grantedById ID del usuario que otorga
     * @param \Carbon\Carbon|null $startDate Fecha de inicio para las cuotas (por defecto próximo mes)
     * @return array Resultado con 'success' y 'message'
     */
    public function activate(int $grantedById, ?\Carbon\Carbon $startDate = null): array
    {
        if (!$this->isPending()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden activar préstamos pendientes.',
            ];
        }

        $startDate = $startDate ?? now()->addMonth()->startOfMonth();

        DB::transaction(function () use ($grantedById, $startDate) {
            // Actualizar el préstamo
            $this->update([
                'status' => 'active',
                'granted_at' => now(),
                'granted_by_id' => $grantedById,
            ]);

            // Generar las cuotas
            $this->generateInstallments($startDate);
        });

        return [
            'success' => true,
            'message' => "Préstamo activado. Se generaron {$this->installments_count} cuotas.",
        ];
    }

    /**
     * Genera las cuotas del préstamo
     *
     * @param \Carbon\Carbon $startDate Fecha de la primera cuota
     */
    protected function generateInstallments(\Carbon\Carbon $startDate): void
    {
        // Eliminar cuotas existentes si las hay
        $this->installments()->delete();

        $dueDate = $startDate->copy();

        for ($i = 1; $i <= $this->installments_count; $i++) {
            LoanInstallment::create([
                'loan_id' => $this->id,
                'installment_number' => $i,
                'amount' => $this->installment_amount,
                'due_date' => $dueDate->copy(),
                'status' => 'pending',
            ]);

            // Siguiente mes
            $dueDate->addMonth();
        }
    }

    /**
     * Cancela el préstamo
     *
     * @param string|null $reason Motivo de la cancelación
     * @return array Resultado con 'success' y 'message'
     */
    public function cancel(?string $reason = null): array
    {
        if ($this->isPaid() || $this->isCancelled() || $this->isDefaulted()) {
            return [
                'success' => false,
                'message' => 'No se puede cancelar un préstamo en este estado.',
            ];
        }

        // Verificar si tiene cuotas pagadas
        if ($this->paid_installments_count > 0) {
            return [
                'success' => false,
                'message' => 'No se puede cancelar un préstamo con cuotas ya pagadas.',
            ];
        }

        DB::transaction(function () use ($reason) {
            // Cancelar cuotas pendientes
            $this->installments()->where('status', 'pending')->update(['status' => 'cancelled']);

            // Actualizar el préstamo
            $this->update([
                'status' => 'cancelled',
                'notes' => $reason ? ($this->notes ? "{$this->notes}\n\nCancelación: {$reason}" : "Cancelación: {$reason}") : $this->notes,
            ]);
        });

        return [
            'success' => true,
            'message' => 'El préstamo ha sido cancelado.',
        ];
    }

    /**
     * Marca el préstamo como incobrable
     *
     * @param string $reason Motivo
     * @return array Resultado con 'success' y 'message'
     */
    public function markAsDefaulted(string $reason): array
    {
        if (!$this->isActive()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden marcar como incobrables los préstamos activos.',
            ];
        }

        DB::transaction(function () use ($reason) {
            // Cancelar cuotas pendientes
            $this->installments()->where('status', 'pending')->update(['status' => 'cancelled']);

            // Actualizar el préstamo
            $this->update([
                'status' => 'defaulted',
                'notes' => $this->notes ? "{$this->notes}\n\nIncobrable: {$reason}" : "Incobrable: {$reason}",
            ]);
        });

        return [
            'success' => true,
            'message' => 'El préstamo ha sido marcado como incobrable.',
        ];
    }

    /**
     * Verifica si todas las cuotas están pagadas y actualiza el estado
     */
    public function checkIfPaid(): void
    {
        if ($this->isActive() && $this->pending_installments_count === 0) {
            $this->update(['status' => 'paid']);
        }
    }

    /**
     * Scope para filtrar por estado
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para filtrar por tipo
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope para obtener solo préstamos
     */
    public function scopeLoans($query)
    {
        return $query->where('type', 'loan');
    }

    /**
     * Scope para obtener solo adelantos
     */
    public function scopeAdvances($query)
    {
        return $query->where('type', 'advance');
    }

    /**
     * Scope para obtener préstamos activos
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope para obtener préstamos pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope para filtrar por empleado
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Obtiene el total de deuda activa de un empleado
     */
    public static function getTotalActiveDebtForEmployee(int $employeeId): float
    {
        return (float) static::where('employee_id', $employeeId)
            ->where('status', 'active')
            ->sum('amount') - (float) LoanInstallment::whereHas('loan', function ($query) use ($employeeId) {
                $query->where('employee_id', $employeeId)->where('status', 'active');
            })->where('status', 'paid')->sum('amount');
    }

    /**
     * Verifica si un empleado tiene un préstamo/adelanto pendiente o activo del tipo especificado
     *
     * @param int $employeeId
     * @param string $type 'loan' o 'advance'
     * @return Loan|null El préstamo existente o null
     */
    public static function getActiveLoanForEmployee(int $employeeId, string $type): ?Loan
    {
        return static::where('employee_id', $employeeId)
            ->where('type', $type)
            ->whereIn('status', ['pending', 'active'])
            ->first();
    }

    /**
     * Obtiene un resumen de préstamos activos de un empleado
     *
     * @param int $employeeId
     * @return array
     */
    public static function getActiveLoansSummaryForEmployee(int $employeeId): array
    {
        $activeLoan = static::getActiveLoanForEmployee($employeeId, 'loan');
        $activeAdvance = static::getActiveLoanForEmployee($employeeId, 'advance');

        return [
            'has_active_loan' => $activeLoan !== null,
            'active_loan' => $activeLoan,
            'has_active_advance' => $activeAdvance !== null,
            'active_advance' => $activeAdvance,
            'total_debt' => static::getTotalActiveDebtForEmployee($employeeId),
        ];
    }
}
