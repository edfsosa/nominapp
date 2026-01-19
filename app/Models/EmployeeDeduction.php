<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EmployeeDeduction extends Pivot
{
    protected $table = 'employee_deductions';

    protected $fillable = [
        'employee_id',
        'deduction_id',
        'start_date',
        'end_date',
        'custom_amount',
        'notes',
    ];

    protected $casts = [
        'start_date'    => 'date',
        'end_date'      => 'date',
        'custom_amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function deduction()
    {
        return $this->belongsTo(Deduction::class);
    }

    /**
     * Relación con el modelo Absent, una deducción puede originarse de una ausencia
     */
    public function absent()
    {
        return $this->hasOne(Absent::class);
    }

    public $incrementing = true;

    /**
     * Scope para obtener solo asignaciones activas
     */
    public function scopeActive($query)
    {
        return $query->whereNull('end_date');
    }

    /**
     * Scope para obtener solo asignaciones inactivas
     */
    public function scopeInactive($query)
    {
        return $query->whereNotNull('end_date');
    }

    /**
     * Scope para filtrar por empleado
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope para filtrar por deducción
     */
    public function scopeForDeduction($query, $deductionId)
    {
        return $query->where('deduction_id', $deductionId);
    }

    /**
     * Verificar si la asignación está activa
     */
    public function isActive(): bool
    {
        return is_null($this->end_date);
    }

    /**
     * Marcar la asignación como inactiva
     */
    public function deactivate(): bool
    {
        return $this->update(['end_date' => now()]);
    }

    /**
     * Reactivar una asignación
     */
    public function reactivate(): bool
    {
        return $this->update(['end_date' => null]);
    }
}
