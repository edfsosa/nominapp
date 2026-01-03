<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Perception extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'calculation',
        'amount',
        'percent',
        'is_taxable',
        'affects_ips',
        'affects_irp',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percent' => 'decimal:2',
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
        'affects_ips' => 'boolean',
        'affects_irp' => 'boolean',
    ];

    /**
     * Relación con el modelo Employee, una percepción puede aplicarse a muchos empleados
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_perceptions')
            ->using(EmployeePerception::class)
            ->withPivot(['start_date', 'end_date', 'custom_amount', 'notes'])
            ->withTimestamps();
    }
}
