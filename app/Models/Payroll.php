<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    /** @use HasFactory<\Database\Factories\PayrollFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'payroll_period_id',
        'base_salary',
        'gross_salary',
        'total_deductions',
        'total_perceptions',
        'net_salary',
        'pdf_path',
        'generated_at',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'total_perceptions' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

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

    // Accesor para mostrar nombre
    public function getTitleAttribute(): string
    {
        return 'Nómina de ' . $this->employee->first_name . ' ' . $this->employee->last_name . ' - ' . $this->period->name;
    }
}
