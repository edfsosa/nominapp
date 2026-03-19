<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'cost_center',
        'description',
    ];

    /**
     * Definir la relación de pertenencia entre el departamento y la empresa a la que pertenece.
     *
     * @return BelongsTo
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Definir la relación de uno a muchos entre el departamento y los cargos que pertenecen a él.
     *
     * @return HasMany
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * Definir la relación de muchos a muchos entre el departamento y los empleados a través de los contratos, permitiendo acceder a los empleados que trabajan en el departamento independientemente de su cargo específico.
     *
     * @return void
     */
    public function employees()
    {
        return $this->hasManyThrough(
            Employee::class,
            Contract::class,
            'department_id', // FK on contracts
            'id',            // FK on employees
            'id',            // local key on departments
            'employee_id'    // local key on contracts
        );
    }
}
