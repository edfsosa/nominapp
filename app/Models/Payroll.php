<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    protected $fillable = [
        'employee_id',
        'net_salary',    // Se calcula automáticamente
        'hours_extra',   // Horas extras registradas
        'days_absent',   // Días de ausencia
        'payment_date',  // Fecha de pago
        'notes'         // Comentarios adicionales
    ];

    // Relación con Employee
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // Relación con Deduction
    public function deductions(): HasMany
    {
        return $this->hasMany(Deduction::class);
    }

    // Relación con Bonus
    public function bonuses(): HasMany
    {
        return $this->hasMany(Bonus::class);
    }

    // Accessor para calcular el salario bruto (gross_salary)
    public function getGrossSalaryAttribute(): float
    {
        $baseSalary = $this->employee->contract_type === 'mensualero'
            ? $this->employee->salary
            : $this->employee->salary * $this->days_worked; // Para jornaleros: salario diario × días trabajados

        $hourlyRate = $this->employee->salary / 160; // 160 horas mensuales (ajusta según necesidad)
        $hoursExtraValue = $this->hours_extra * $hourlyRate;

        return $baseSalary + $hoursExtraValue;
    }
    // Método para calcular el salario neto
    public function calculateNetSalary(): void
    {
        // Calcular salario bruto (base + horas extras)
        $grossSalary = $this->gross_salary;

        // Deducción IPS (9% del salario bruto)
        $ipsDeduction = $grossSalary * 0.09;

        // Otras deducciones (si existen)
        $otherDeductions = $this->deductions->where('type', 'fixed')->sum('amount');

        // Calcular salario neto
        $this->net_salary = $grossSalary - $ipsDeduction - $otherDeductions;
    }

    // Lógica automática al guardar/actualizar
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Payroll $payroll) {
            // Asegurar que las relaciones estén cargadas
            if (!$payroll->relationLoaded('deductions')) {
                $payroll->load('deductions');
            }
            if (!$payroll->relationLoaded('bonuses')) {
                $payroll->load('bonuses');
            }

            $payroll->calculateNetSalary();
        });
    }
}
