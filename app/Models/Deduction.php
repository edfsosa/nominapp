<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Deduction extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'calculation',
        'amount',
        'percent',
        'is_mandatory',
        'is_active',
        'affects_ips',
        'affects_irp',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percent' => 'decimal:2',
        'is_mandatory' => 'boolean',
        'is_active' => 'boolean',
        'affects_ips' => 'boolean',
        'affects_irp' => 'boolean',
    ];

    /**
     * Relación con el modelo Employee, una deducción puede aplicarse a muchos empleados
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_deductions')
            ->using(EmployeeDeduction::class)
            ->withPivot(['start_date', 'end_date', 'custom_amount', 'notes'])
            ->withTimestamps();
    }
}
