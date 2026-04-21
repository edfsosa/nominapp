<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Préstamo otorgado a un empleado, pagadero en cuotas mensuales.
 *
 * Ciclo de vida: pending → activate() → active → [todas las cuotas pagadas] → paid
 *               active → markAsDefaulted() → defaulted → reactivate() → active
 *               pending/active/defaulted → cancel() → cancelled
 *
 * Las cuotas se generan con amortización francesa: cuota fija con desglose
 * de capital e interés por período. Con tasa 0%, todas las cuotas son iguales
 * y el desglose es capital = cuota, interés = 0.
 */
class Loan extends Model
{
    protected $fillable = [
        'employee_id',
        'amount',
        'interest_rate',
        'installments_count',
        'installment_amount',
        'outstanding_balance',
        'status',
        'reason',
        'granted_at',
        'granted_by_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'granted_at' => 'date',
    ];

    // =========================================================================
    // RELACIONES
    // =========================================================================

    /** Empleado al que se otorgó el préstamo. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Usuario que otorgó/activó el préstamo. */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_id');
    }

    /** Cuotas del préstamo ordenadas por número. */
    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('installment_number');
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
            'active' => 'Activo',
            'paid' => 'Pagado',
            'cancelled' => 'Cancelado',
            'defaulted' => 'En Mora',
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
            'active' => 'info',
            'paid' => 'success',
            'cancelled' => 'gray',
            'defaulted' => 'danger',
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
            'active' => 'heroicon-o-play',
            'paid' => 'heroicon-o-check-circle',
            'cancelled' => 'heroicon-o-x-circle',
            'defaulted' => 'heroicon-o-exclamation-triangle',
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
            'active' => 'Activo',
            'paid' => 'Pagado',
            'cancelled' => 'Cancelado',
            'defaulted' => 'En Mora',
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

    public function isDefaulted(): bool
    {
        return $this->status === 'defaulted';
    }

    // =========================================================================
    // ATRIBUTOS COMPUTADOS
    // =========================================================================

    /**
     * Suma de capital pagado (cuotas con status=paid).
     */
    public function getPaidAmountAttribute(): float
    {
        return (float) $this->installments()->where('status', 'paid')->sum('capital_amount');
    }

    /**
     * Cantidad de cuotas pagadas.
     */
    public function getPaidInstallmentsCountAttribute(): int
    {
        return $this->installments()->where('status', 'paid')->count();
    }

    /**
     * Cantidad de cuotas pendientes.
     */
    public function getPendingInstallmentsCountAttribute(): int
    {
        return $this->installments()->where('status', 'pending')->count();
    }

    /**
     * Descripción del progreso de pago: "X/Y cuotas".
     */
    public function getProgressDescriptionAttribute(): string
    {
        return "{$this->paid_installments_count}/{$this->installments_count} cuotas";
    }

    /**
     * Porcentaje de avance del pago (0–100).
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->installments_count === 0) {
            return 0;
        }

        return (int) round(($this->paid_installments_count / $this->installments_count) * 100);
    }

    /**
     * Indica si el préstamo tiene interés.
     */
    public function hasInterest(): bool
    {
        return (float) $this->interest_rate > 0;
    }

    /**
     * Retorna el label del estado actual.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabel($this->status);
    }

    // =========================================================================
    // CÁLCULO DE CUOTA (PMT — AMORTIZACIÓN FRANCESA)
    // =========================================================================

    /**
     * Calcula la cuota mensual fija usando la fórmula PMT de amortización francesa.
     *
     * Con tasa 0% retorna simplemente amount / installments_count.
     * La cuota calculada se almacena en installment_amount al activar.
     *
     * @return float Monto de cuota redondeado a 2 decimales.
     */
    public function calculatePmt(): float
    {
        $principal = (float) $this->amount;
        $n = (int) $this->installments_count;
        $annualRate = (float) $this->interest_rate;

        if ($n === 0) {
            return 0.0;
        }

        if ($annualRate <= 0) {
            return round($principal / $n, 2);
        }

        $r = ($annualRate / 100) / 12; // Tasa mensual
        $pmt = $principal * $r * pow(1 + $r, $n) / (pow(1 + $r, $n) - 1);

        return round($pmt, 2);
    }

    // =========================================================================
    // ACCIONES PRINCIPALES
    // =========================================================================

    /**
     * Activa el préstamo, calcula el PMT y genera el plan de cuotas.
     *
     * Valida:
     *  - Estado pending
     *  - Contrato activo del empleado
     *  - Límite legal del 25% del salario (Art. 245 CLT) por cuota
     *
     * @param  int  $grantedById  ID del usuario que otorga el préstamo.
     * @return array{success: bool, message: string}
     */
    public function activate(int $grantedById): array
    {
        if (! $this->isPending()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden activar préstamos en estado Pendiente.',
            ];
        }

        if (! $this->employee->activeContract) {
            return [
                'success' => false,
                'message' => 'El empleado no tiene un contrato activo.',
            ];
        }

        // Calcular PMT con la tasa de interés actual
        $pmt = $this->calculatePmt();

        // Validar límite de cuota como % del salario (configurable en PayrollSettings)
        $salaryBase = $this->getLoanDeductionBase();
        if ($salaryBase > 0) {
            $capPercent = app(\App\Settings\PayrollSettings::class)->loan_installment_cap_percent;
            $cap = round($salaryBase * $capPercent / 100, 2);
            if ($pmt > $cap) {
                $capFormatted = number_format($cap, 0, ',', '.');
                $pmtFormatted = number_format($pmt, 0, ',', '.');

                return [
                    'success' => false,
                    'message' => "La cuota de Gs. {$pmtFormatted} supera el límite del {$capPercent}% del salario (máximo Gs. {$capFormatted}). Reducí el monto, aumentá la cantidad de cuotas o ajustá la tasa.",
                ];
            }
        }

        $startDate = $this->calculateNextPayrollDate();

        DB::transaction(function () use ($grantedById, $pmt, $startDate) {
            $this->update([
                'status' => 'active',
                'granted_at' => now(),
                'granted_by_id' => $grantedById,
                'installment_amount' => $pmt,
                'outstanding_balance' => $this->amount,
            ]);

            $this->generateInstallments($startDate, $pmt);
        });

        $formattedDate = $startDate->format('d/m/Y');

        return [
            'success' => true,
            'message' => 'Préstamo activado. Primera cuota de Gs. '.number_format($pmt, 0, ',', '.')." programada para {$formattedDate}.",
        ];
    }

    /**
     * Cancela el préstamo.
     *
     * Las cuotas ya pagadas se conservan; solo se cancelan las pendientes.
     *
     * @param  string|null  $reason  Motivo de cancelación.
     * @return array{success: bool, message: string}
     */
    public function cancel(?string $reason = null): array
    {
        if ($this->isPaid() || $this->isCancelled()) {
            return [
                'success' => false,
                'message' => 'No se puede cancelar un préstamo en este estado.',
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
            'message' => 'El préstamo ha sido cancelado.',
        ];
    }

    /**
     * Marca el préstamo como en mora.
     *
     * Las cuotas pendientes se conservan para cobro posterior.
     * Solo aplica a préstamos activos.
     *
     * @param  string|null  $reason  Motivo de la mora.
     * @return array{success: bool, message: string}
     */
    public function markAsDefaulted(?string $reason = null): array
    {
        if (! $this->isActive()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden marcar como en mora los préstamos activos.',
            ];
        }

        $notes = $this->notes;
        if ($reason) {
            $notes = $notes ? "{$notes}\n\nMora: {$reason}" : "Mora: {$reason}";
        }

        $this->update([
            'status' => 'defaulted',
            'notes' => $notes,
        ]);

        return [
            'success' => true,
            'message' => 'El préstamo fue marcado como en mora.',
        ];
    }

    /**
     * Reactiva un préstamo en mora para continuar el cobro de cuotas pendientes.
     *
     * @return array{success: bool, message: string}
     */
    public function reactivate(): array
    {
        if (! $this->isDefaulted()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden reactivar préstamos en mora.',
            ];
        }

        if ($this->pending_installments_count === 0) {
            return [
                'success' => false,
                'message' => 'No hay cuotas pendientes para reactivar.',
            ];
        }

        $this->update(['status' => 'active']);

        return [
            'success' => true,
            'message' => "Préstamo reactivado. Continúa con {$this->pending_installments_count} cuota(s) pendiente(s).",
        ];
    }

    /**
     * Verifica si todas las cuotas están pagadas y cierra el préstamo.
     *
     * Llamado por LoanInstallmentCalculator::markInstallmentsAsPaid().
     */
    public function checkIfPaid(): void
    {
        if ($this->isActive() && $this->pending_installments_count === 0) {
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
     * Base salarial mensual para calcular el límite del 25% (Art. 245 CLT).
     *
     * Para mensualeros usa el salario base del contrato.
     * Para jornaleros usa daily_rate × 30 como equivalente mensual aproximado.
     */
    protected function getLoanDeductionBase(): float
    {
        if ($this->employee->base_salary && $this->employee->base_salary > 0) {
            return (float) $this->employee->base_salary;
        }

        if ($this->employee->daily_rate && $this->employee->daily_rate > 0) {
            return round((float) $this->employee->daily_rate * 30, 2);
        }

        return 0.0;
    }

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

    /**
     * Retorna el próximo lunes desde una fecha dada.
     */
    protected function getNextMonday(Carbon $from): Carbon
    {
        $next = $from->copy();

        return $next->isMonday()
            ? $next->addWeek()->startOfDay()
            : $next->next(Carbon::MONDAY)->startOfDay();
    }

    /**
     * Retorna la próxima fecha quincenal (día 16 o 1° del mes siguiente).
     */
    protected function getNextBiweeklyDate(Carbon $from): Carbon
    {
        $next = $from->copy();

        return $next->day < 16
            ? $next->day(16)->startOfDay()
            : $next->addMonth()->startOfMonth();
    }

    /**
     * Genera el plan de cuotas con amortización francesa.
     *
     * Cada cuota almacena su monto total, la porción de capital y la de interés.
     * La última cuota absorbe cualquier diferencia de redondeo acumulada.
     *
     * @param  Carbon  $startDate  Fecha de vencimiento de la primera cuota.
     * @param  float  $pmt  Cuota fija calculada (PMT).
     */
    protected function generateInstallments(Carbon $startDate, float $pmt): void
    {
        $this->installments()->delete();

        $principal = (float) $this->amount;
        $n = (int) $this->installments_count;
        $annualRate = (float) $this->interest_rate;
        $monthlyRate = $annualRate > 0 ? ($annualRate / 100) / 12 : 0;
        $balance = $principal;
        $dueDate = $startDate->copy();

        for ($i = 1; $i <= $n; $i++) {
            $interestAmount = round($balance * $monthlyRate, 2);

            if ($i === $n) {
                // Última cuota: absorbe el saldo restante exacto
                $capitalAmount = round($balance, 2);
                $installmentAmount = round($capitalAmount + $interestAmount, 2);
            } else {
                $capitalAmount = round($pmt - $interestAmount, 2);
                $installmentAmount = $pmt;
            }

            $balance -= $capitalAmount;

            LoanInstallment::create([
                'loan_id' => $this->id,
                'installment_number' => $i,
                'amount' => $installmentAmount,
                'capital_amount' => $capitalAmount,
                'interest_amount' => $interestAmount,
                'due_date' => $dueDate->copy(),
                'status' => 'pending',
            ]);

            $dueDate->addMonth();
        }
    }

    /**
     * Verifica si existe nómina para el empleado en el período dado.
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
     * Filtra préstamos activos.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Filtra préstamos pendientes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Filtra préstamos de un empleado específico.
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
     * Retorna el préstamo activo o pendiente de un empleado, si existe.
     */
    public static function getActiveForEmployee(int $employeeId): ?self
    {
        return static::where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'active'])
            ->first();
    }

    /**
     * Obtiene la deuda activa total de un empleado usando outstanding_balance.
     */
    public static function getTotalActiveDebtForEmployee(int $employeeId): float
    {
        return (float) static::where('employee_id', $employeeId)
            ->whereIn('status', ['active', 'defaulted'])
            ->sum('outstanding_balance');
    }

    /**
     * Cantidad de préstamos pendientes de activación (para badge de navegación).
     */
    public static function getPendingCount(): int
    {
        return static::where('status', 'pending')->count();
    }
}
