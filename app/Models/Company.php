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
        'legal_type',
        'founded_at',
        'legal_rep_name',
        'legal_rep_ci',
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
        'is_active'  => 'boolean',
        'founded_at' => 'date',
    ];

    public static array $legalTypes = [
        'SA'          => 'Sociedad Anónima (SA)',
        'SRL'         => 'Sociedad de Resp. Limitada (SRL)',
        'SACI'        => 'Sociedad Anónima de Cap. e Industria (SACI)',
        'SC'          => 'Sociedad Colectiva (SC)',
        'EU'          => 'Empresa Unipersonal',
        'Cooperativa' => 'Cooperativa',
        'Fundacion'   => 'Fundación',
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
     * Label del tipo societario
     */
    public function getLegalTypeLabelAttribute(): ?string
    {
        return $this->legal_type ? (static::$legalTypes[$this->legal_type] ?? $this->legal_type) : null;
    }

    /**
     * Contratos activos de todos los empleados de la empresa
     */
    public function activeContractsCount(): int
    {
        return Contract::whereIn(
            'employee_id', $this->employees()->select('employees.id')
        )->where('status', 'active')->count();
    }

    /**
     * Contratos activos a plazo fijo que vencen dentro de X días
     */
    public function expiringSoonContractsCount(int $days = 30): int
    {
        return Contract::whereIn(
            'employee_id', $this->employees()->select('employees.id')
        )->where('status', 'active')
         ->whereNotNull('end_date')
         ->where('end_date', '<=', now()->addDays($days))
         ->count();
    }

    /**
     * Scope para empresas activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
