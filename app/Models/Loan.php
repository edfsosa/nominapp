<?php

namespace App\Models;

use Carbon\Carbon;
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

    // =========================================================================
    // RELACIONES
    // =========================================================================

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }

    // =========================================================================
    // HELPERS ESTÁTICOS PARA TIPOS
    // =========================================================================

    public static function getTypeLabel(string $type): string
    {
        return match ($type) {
            'loan' => 'Préstamo',
            'advance' => 'Adelanto',
            default => 'Desconocido',
        };
    }

    public static function getTypeColor(string $type): string
    {
        return match ($type) {
            'loan' => 'info',
            'advance' => 'warning',
            default => 'gray',
        };
    }

    public static function getTypeIcon(string $type): string
    {
        return match ($type) {
            'loan' => 'heroicon-o-banknotes',
            'advance' => 'heroicon-o-clock',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    public static function getTypeOptions(): array
    {
        return [
            'loan' => 'Préstamo',
            'advance' => 'Adelanto de Salario',
        ];
    }

    // =========================================================================
    // HELPERS ESTÁTICOS PARA ESTADOS
    // =========================================================================

    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'active' => 'Activo',
            'paid' => 'Pagado',
            'cancelled' => 'Cancelado',
            default => 'Desconocido',
        };
    }

    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'active' => 'info',
            'paid' => 'success',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    public static function getStatusIcon(string $status): string
    {
        return match ($status) {
            'pending' => 'heroicon-o-clock',
            'active' => 'heroicon-o-play',
            'paid' => 'heroicon-o-check-circle',
            'cancelled' => 'heroicon-o-x-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    public static function getStatusOptions(): array
    {
        return [
            'pending' => 'Pendiente',
            'active' => 'Activo',
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

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isLoan(): bool
    {
        return $this->type === 'loan';
    }

    public function isAdvance(): bool
    {
        return $this->type === 'advance';
    }

    // =========================================================================
    // ATRIBUTOS COMPUTADOS
    // =========================================================================

    public function getPaidAmountAttribute(): float
    {
        return (float) $this->installments()->where('status', 'paid')->sum('amount');
    }

    public function getPendingAmountAttribute(): float
    {
        return (float) $this->installments()->where('status', 'pending')->sum('amount');
    }

    public function getPaidInstallmentsCountAttribute(): int
    {
        return $this->installments()->where('status', 'paid')->count();
    }

    public function getPendingInstallmentsCountAttribute(): int
    {
        return $this->installments()->where('status', 'pending')->count();
    }

    public function getProgressDescriptionAttribute(): string
    {
        return "{$this->paid_installments_count}/{$this->installments_count} cuotas";
    }

    public function getProgressPercentageAttribute(): int
    {
        if ($this->installments_count === 0) {
            return 0;
        }

        return (int) round(($this->paid_installments_count / $this->installments_count) * 100);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::getTypeLabel($this->type);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabel($this->status);
    }

    // =========================================================================
    // ACCIONES PRINCIPALES
    // =========================================================================

    /**
     * Activa el préstamo/adelanto y genera las cuotas
     */
    public function activate(int $grantedById): array
    {
        if (!$this->isPending()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden activar préstamos/adelantos pendientes.',
            ];
        }

        // Para adelantos: verificar que no exista nómina del período actual
        if ($this->isAdvance()) {
            $currentPeriod = $this->getCurrentPeriod();

            if ($currentPeriod && $this->payrollExistsForPeriod($currentPeriod)) {
                return [
                    'success' => false,
                    'message' => 'No se puede activar el adelanto porque la nómina del período actual ya fue generada para este empleado.',
                ];
            }
        }

        // Calcular fecha de cuota
        $startDate = $this->isAdvance()
            ? $this->getCurrentPeriodEndDate()
            : $this->calculateNextPayrollDate();

        DB::transaction(function () use ($grantedById, $startDate) {
            $this->update([
                'status' => 'active',
                'granted_at' => now(),
                'granted_by_id' => $grantedById,
            ]);

            $this->generateInstallments($startDate);
        });

        $formattedDate = $startDate->format('d/m/Y');
        $type = $this->type_label;

        if ($this->isAdvance()) {
            return [
                'success' => true,
                'message' => "{$type} activado. Vence el {$formattedDate} y se descontará en la nómina del período actual.",
            ];
        }

        return [
            'success' => true,
            'message' => "{$type} activado. Primera cuota programada para {$formattedDate}.",
        ];
    }

    /**
     * Cancela el préstamo/adelanto
     */
    public function cancel(?string $reason = null): array
    {
        if ($this->isPaid() || $this->isCancelled()) {
            return [
                'success' => false,
                'message' => 'No se puede cancelar un préstamo/adelanto en este estado.',
            ];
        }

        if ($this->paid_installments_count > 0) {
            return [
                'success' => false,
                'message' => 'No se puede cancelar un préstamo/adelanto con cuotas ya pagadas.',
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
                'notes' => $notes,
            ]);
        });

        return [
            'success' => true,
            'message' => "El {$this->type_label} ha sido cancelado.",
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

    // =========================================================================
    // MÉTODOS DE PERÍODO Y FECHAS
    // =========================================================================

    /**
     * Obtiene el período de nómina actual del empleado
     */
    protected function getCurrentPeriod(): ?PayrollPeriod
    {
        $now = Carbon::now();

        return PayrollPeriod::where('frequency', $this->employee->payroll_type)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->first();
    }

    /**
     * Verifica si existe nómina para un período específico
     */
    protected function payrollExistsForPeriod(PayrollPeriod $period): bool
    {
        return Payroll::where('employee_id', $this->employee_id)
            ->where('payroll_period_id', $period->id)
            ->exists();
    }

    /**
     * Obtiene la fecha de fin del período actual
     */
    protected function getCurrentPeriodEndDate(): Carbon
    {
        $currentPeriod = $this->getCurrentPeriod();

        if ($currentPeriod) {
            return Carbon::parse($currentPeriod->end_date);
        }

        // Fallback: calcular fecha de fin según tipo de nómina
        $now = Carbon::now();
        $payrollType = $this->employee->payroll_type;

        return match ($payrollType) {
            'weekly' => $now->copy()->endOfWeek(Carbon::SUNDAY),
            'biweekly' => $now->day <= 15
                ? $now->copy()->day(15)->endOfDay()
                : $now->copy()->endOfMonth(),
            default => $now->copy()->endOfMonth(),
        };
    }

    /**
     * Calcula la fecha del próximo período de nómina
     */
    protected function calculateNextPayrollDate(): Carbon
    {
        $now = Carbon::now();
        $payrollType = $this->employee->payroll_type;

        return match ($payrollType) {
            'weekly' => $this->getNextMonday($now),
            'biweekly' => $this->getNextBiweeklyDate($now),
            default => $now->copy()->addMonth()->startOfMonth(),
        };
    }

    protected function getNextMonday(Carbon $from): Carbon
    {
        $next = $from->copy();

        if ($next->isMonday()) {
            $next->addWeek();
        } else {
            $next->next(Carbon::MONDAY);
        }

        return $next->startOfDay();
    }

    protected function getNextBiweeklyDate(Carbon $from): Carbon
    {
        $next = $from->copy();

        if ($next->day < 16) {
            return $next->day(16)->startOfDay();
        }

        return $next->addMonth()->startOfMonth();
    }

    /**
     * Genera las cuotas del préstamo
     */
    protected function generateInstallments(Carbon $startDate): void
    {
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

            $dueDate->addMonth();
        }
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeLoans($query)
    {
        return $query->where('type', 'loan');
    }

    public function scopeAdvances($query)
    {
        return $query->where('type', 'advance');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    // =========================================================================
    // MÉTODOS ESTÁTICOS DE CONSULTA
    // =========================================================================

    /**
     * Obtiene el total de deuda activa de un empleado
     */
    public static function getTotalActiveDebtForEmployee(int $employeeId): float
    {
        $totalLoaned = (float) static::where('employee_id', $employeeId)
            ->where('status', 'active')
            ->sum('amount');

        $totalPaid = (float) LoanInstallment::whereHas('loan', function ($query) use ($employeeId) {
            $query->where('employee_id', $employeeId)->where('status', 'active');
        })->where('status', 'paid')->sum('amount');

        return $totalLoaned - $totalPaid;
    }

    /**
     * Obtiene préstamo/adelanto activo o pendiente de un empleado
     */
    public static function getActiveLoanForEmployee(int $employeeId, string $type): ?Loan
    {
        return static::where('employee_id', $employeeId)
            ->where('type', $type)
            ->whereIn('status', ['pending', 'active'])
            ->first();
    }

    /**
     * Obtiene resumen de préstamos activos de un empleado
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

    /**
     * Obtiene conteos agrupados por estado y tipo para optimizar badges
     */
    public static function getCounts(): array
    {
        $counts = static::selectRaw('status, type, COUNT(*) as count')
            ->groupBy('status', 'type')
            ->get();

        $result = [
            'total' => 0,
            'by_status' => ['pending' => 0, 'active' => 0, 'paid' => 0, 'cancelled' => 0],
            'by_type' => ['loan' => 0, 'advance' => 0],
        ];

        foreach ($counts as $count) {
            $result['total'] += $count->count;
            $result['by_status'][$count->status] = ($result['by_status'][$count->status] ?? 0) + $count->count;
            $result['by_type'][$count->type] = ($result['by_type'][$count->type] ?? 0) + $count->count;
        }

        return $result;
    }
}
