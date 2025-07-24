<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Perception extends Model
{
    protected $fillable = [
        'name',
        'description',
        'calculation',
        'value',
        'applies_to_all',
    ];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(
            Employee::class,
            'employee_perception',
            'perception_id',
            'employee_id'
        )->withPivot('effective_from', 'effective_to', 'value_override')->withTimestamps();
    }

    // Calcular monto para un empleado específico
    public function calculateFor(Employee $employee)
    {
        if ($this->type === 'percentage') {
            // Si es un porcentaje, calcular sobre el salario del empleado
            return $employee->base_salary * ($this->value / 100);
        } elseif ($this->calculation === 'fixed') {
            // Si es un monto fijo, retornar el valor directamente
            return $this->value;
        }
    }
}
