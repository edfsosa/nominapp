<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'ci',
        'first_name',
        'last_name',
        'email',
        'salary',
        'department',
        'branch',
        'contract_type',
        'hire_date',
        'status'
    ];

    // Accessor para nombre completo (opcional)
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    // Relación con Payroll
    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }
}
