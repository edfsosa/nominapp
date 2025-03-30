<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'ci',
        'first_name',
        'last_name',
        'email',
        'salary',
        'department',
        'hire_date',
        'status'
    ];

    // Accessor para nombre completo (opcional)
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    // Relación con User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relación con Payroll
/*     public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    } */
}
