<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Company extends Model
{
    protected $fillable = [
        'name',
        'trade_name',
        'ruc',
        'employer_number',
        'logo',
        'address',
        'phone',
        'email',
        'city',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Sucursales de la empresa
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Empleados de la empresa (a través de sucursales)
     */
    public function employees(): HasManyThrough
    {
        return $this->hasManyThrough(Employee::class, Branch::class);
    }

    /**
     * Nombre para mostrar (comercial o razón social)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->trade_name ?? $this->name;
    }

    /**
     * Scope para empresas activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
