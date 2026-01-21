<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VacationBalance extends Model
{
    protected $table = 'employee_vacation_balances';

    protected $fillable = [
        'employee_id',
        'year',
        'years_of_service',
        'entitled_days',
        'used_days',
        'pending_days',
        'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'years_of_service' => 'integer',
        'entitled_days' => 'integer',
        'used_days' => 'integer',
        'pending_days' => 'integer',
    ];

    /**
     * Relación con el empleado
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relación con las vacaciones asociadas a este balance
     */
    public function vacations(): HasMany
    {
        return $this->hasMany(Vacation::class);
    }

    /**
     * Calcula los días disponibles
     */
    public function getAvailableDaysAttribute(): int
    {
        return max(0, $this->entitled_days - $this->used_days - $this->pending_days);
    }

    /**
     * Descripción del balance
     */
    public function getBalanceDescriptionAttribute(): string
    {
        return "{$this->used_days}/{$this->entitled_days} días usados";
    }

    /**
     * Descripción completa del balance
     */
    public function getFullDescriptionAttribute(): string
    {
        $available = $this->available_days;
        $pending = $this->pending_days > 0 ? " ({$this->pending_days} pendientes)" : '';

        return "Disponibles: {$available} días{$pending} | Usados: {$this->used_days}/{$this->entitled_days}";
    }

    /**
     * Verifica si tiene días disponibles
     */
    public function hasAvailableDays(int $days = 1): bool
    {
        return $this->available_days >= $days;
    }

    /**
     * Incrementa los días pendientes (al crear solicitud)
     */
    public function addPendingDays(int $days): void
    {
        $this->increment('pending_days', $days);
    }

    /**
     * Confirma días pendientes como usados (al aprobar solicitud)
     */
    public function confirmDays(int $days): void
    {
        $this->decrement('pending_days', $days);
        $this->increment('used_days', $days);
    }

    /**
     * Libera días pendientes (al rechazar/cancelar solicitud)
     */
    public function releasePendingDays(int $days): void
    {
        $this->decrement('pending_days', min($days, $this->pending_days));
    }

    /**
     * Devuelve días usados (al cancelar vacaciones aprobadas)
     */
    public function returnUsedDays(int $days): void
    {
        $this->decrement('used_days', min($days, $this->used_days));
    }

    /**
     * Obtiene la etiqueta de días según antigüedad
     */
    public static function getEntitledDaysLabel(int $yearsOfService): string
    {
        if ($yearsOfService < 1) {
            return 'Sin derecho (menos de 1 año)';
        } elseif ($yearsOfService <= 5) {
            return '12 días hábiles';
        } elseif ($yearsOfService <= 10) {
            return '18 días hábiles';
        } else {
            return '30 días hábiles';
        }
    }
}
