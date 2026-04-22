<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class Payroll extends Model
{
    /** @use HasFactory<\Database\Factories\PayrollFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'payroll_period_id',
        'base_salary',
        'gross_salary',
        'total_deductions',
        'total_perceptions',
        'ips_perceptions',
        'net_salary',
        'pdf_path',
        'generated_at',
        'status',
        'approved_by_id',
        'approved_at',
        'bank_account_id',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'total_perceptions' => 'decimal:2',
        'ips_perceptions' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'generated_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Payroll $payroll) {
            if (in_array($payroll->status, ['approved', 'paid'])) {
                throw new \Exception("No se puede eliminar una nómina con estado '{$payroll->status}'.");
            }

            $period = $payroll->period;
            if ($period) {
                // --- PRÉSTAMOS: revertir cuotas pagadas en este período ---

                // Recopilar employee_deduction_id de cuotas a revertir antes de actualizarlas
                $deductionIds = LoanInstallment::whereHas('loan', fn ($q) => $q
                    ->where('employee_id', $payroll->employee_id))
                    ->whereBetween('due_date', [$period->start_date, $period->end_date])
                    ->whereNotNull('employee_deduction_id')
                    ->pluck('employee_deduction_id')
                    ->filter();

                LoanInstallment::whereHas('loan', fn ($q) => $q
                    ->where('employee_id', $payroll->employee_id)
                    ->where('status', 'approved'))
                    ->where('status', 'paid')
                    ->whereBetween('due_date', [$period->start_date, $period->end_date])
                    ->update(['status' => 'pending', 'paid_at' => null, 'payroll_id' => null]);

                // Restaurar outstanding_balance en los préstamos afectados
                LoanInstallment::whereHas('loan', fn ($q) => $q
                    ->where('employee_id', $payroll->employee_id))
                    ->whereBetween('due_date', [$period->start_date, $period->end_date])
                    ->where('status', 'pending')
                    ->with('loan')
                    ->get()
                    ->groupBy('loan_id')
                    ->each(function ($installments) {
                        $loan = $installments->first()->loan;
                        if ($loan && $loan->status === 'approved') {
                            $pendingCapital = $loan->installments()
                                ->where('status', 'pending')
                                ->sum('capital_amount');
                            $loan->update(['outstanding_balance' => $pendingCapital]);
                        }
                    });

                // Eliminar EmployeeDeduction puntuales de cuotas.
                // La FK nullOnDelete limpia automáticamente loan_installments.employee_deduction_id.
                if ($deductionIds->isNotEmpty()) {
                    EmployeeDeduction::whereIn('id', $deductionIds)->delete();
                }

                // --- ADELANTOS: revertir adelanto descontado en esta nómina ---

                $advanceDeductionIds = Advance::where('employee_id', $payroll->employee_id)
                    ->where('payroll_id', $payroll->id)
                    ->whereNotNull('employee_deduction_id')
                    ->pluck('employee_deduction_id')
                    ->filter();

                Advance::where('employee_id', $payroll->employee_id)
                    ->where('payroll_id', $payroll->id)
                    ->update([
                        'status' => 'approved',
                        'payroll_id' => null,
                        'employee_deduction_id' => null,
                    ]);

                if ($advanceDeductionIds->isNotEmpty()) {
                    EmployeeDeduction::whereIn('id', $advanceDeductionIds)->delete();
                }

                // --- MERCADERÍA: revertir cuotas descontadas en esta nómina ---

                $merchandiseDeductionIds = MerchandiseWithdrawalInstallment::whereHas('withdrawal', fn ($q) => $q
                    ->where('employee_id', $payroll->employee_id))
                    ->whereBetween('due_date', [$period->start_date, $period->end_date])
                    ->whereNotNull('employee_deduction_id')
                    ->pluck('employee_deduction_id')
                    ->filter();

                MerchandiseWithdrawalInstallment::whereHas('withdrawal', fn ($q) => $q
                    ->where('employee_id', $payroll->employee_id)
                    ->where('status', 'approved'))
                    ->where('status', 'paid')
                    ->whereBetween('due_date', [$period->start_date, $period->end_date])
                    ->update(['status' => 'pending', 'paid_at' => null, 'payroll_id' => null]);

                if ($merchandiseDeductionIds->isNotEmpty()) {
                    EmployeeDeduction::whereIn('id', $merchandiseDeductionIds)->delete();
                }

                Log::warning('Cuotas de préstamo, adelantos y mercadería revertidos al eliminar nómina', [
                    'payroll_id' => $payroll->id,
                    'employee_id' => $payroll->employee_id,
                    'period_id' => $period->id,
                ]);
            }
        });
    }

    /**
     * Relación con el modelo Employee, una nómina pertenece a un empleado
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relación con el modelo PayrollPeriod, una nómina pertenece a un período de nómina
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /**
     * Relación con el modelo PayrollItem, una nómina tiene muchos items
     */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    /**
     * Usuario que aprobó la nómina
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /** Cuenta bancaria utilizada para el pago de esta nómina. */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(EmployeeBankAccount::class, 'bank_account_id');
    }

    // Accesor para mostrar nombre
    public function getTitleAttribute(): string
    {
        return 'Nómina de '.($this->employee?->first_name ?? '').' '.($this->employee?->last_name ?? '').' - '.($this->period?->name ?? 'Sin período');
    }

    /**
     * Formatea un monto en guaraníes paraguayos
     * Ejemplo: 1500000 -> "Gs. 1.500.000"
     */
    public static function formatCurrency(float|int|null $amount): string
    {
        if ($amount === null) {
            return 'Gs. 0';
        }

        return 'Gs. '.number_format($amount, 0, ',', '.');
    }

    /**
     * Formatea el salario base en guaraníes
     */
    public function getFormattedBaseSalaryAttribute(): string
    {
        return self::formatCurrency($this->base_salary);
    }

    /**
     * Formatea el total de percepciones en guaraníes
     */
    public function getFormattedTotalPerceptionsAttribute(): string
    {
        return '+ '.self::formatCurrency($this->total_perceptions);
    }

    /**
     * Formatea el salario bruto en guaraníes
     */
    public function getFormattedGrossSalaryAttribute(): string
    {
        return self::formatCurrency($this->gross_salary);
    }

    /**
     * Formatea el total de deducciones en guaraníes
     */
    public function getFormattedTotalDeductionsAttribute(): string
    {
        return '- '.self::formatCurrency($this->total_deductions);
    }

    /**
     * Formatea el salario neto en guaraníes
     */
    public function getFormattedNetSalaryAttribute(): string
    {
        return self::formatCurrency($this->net_salary);
    }
}
