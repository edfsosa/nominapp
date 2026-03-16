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
        'amount'      => 'integer',
        'percent'     => 'decimal:2',
        'is_taxable'  => 'boolean',
        'is_active'   => 'boolean',
        'affects_ips' => 'boolean',
        'affects_irp' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Empleados activos con esta percepción: ya iniciadas y sin fecha de fin vencida.
     */
    public function activeEmployees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_perceptions')
            ->wherePivot('start_date', '<=', now())
            ->where(fn($q) => $q
                ->whereNull('employee_perceptions.end_date')
                ->orWhere('employee_perceptions.end_date', '>=', now())
            );
    }

    /**
     * Todos los empleados alguna vez asignados a esta percepción.
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_perceptions')
            ->using(EmployeePerception::class)
            ->withPivot(['start_date', 'end_date', 'custom_amount', 'notes'])
            ->withTimestamps();
    }

    /**
     * Historial completo de asignaciones.
     */
    public function employeePerceptions()
    {
        return $this->hasMany(EmployeePerception::class);
    }

    /**
     * Asignaciones activas: ya iniciadas y sin fecha de fin o con fecha de fin futura.
     */
    public function activeEmployeePerceptions()
    {
        return $this->hasMany(EmployeePerception::class)
            ->where('start_date', '<=', now())
            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()));
    }

    /**
     * Asignaciones inactivas: con fecha de fin ya vencida.
     */
    public function inactiveEmployeePerceptions()
    {
        return $this->hasMany(EmployeePerception::class)
            ->whereNotNull('end_date')
            ->where('end_date', '<', now());
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Solo percepciones habilitadas.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Solo percepciones deshabilitadas.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Verificar si el cálculo es de monto fijo.
     */
    public function isFixed(): bool
    {
        return $this->calculation === 'fixed';
    }

    /**
     * Verificar si el cálculo es por porcentaje del salario.
     */
    public function isPercentage(): bool
    {
        return $this->calculation === 'percentage';
    }

    public static function formatPercent(mixed $value): ?string
    {
        return $value !== null ? number_format((float) $value, 2) . '%' : null;
    }

    /**
     * Calcular el monto de esta percepción dado un salario base.
     * Prioridad: monto personalizado > porcentaje > monto fijo.
     */
    public function calculateAmount(int $baseSalary, ?int $customAmount = null): int
    {
        if ($customAmount !== null) {
            return $customAmount;
        }

        if ($this->isPercentage()) {
            return (int) round($baseSalary * ($this->percent / 100));
        }

        return $this->amount ?? 0;
    }
}
