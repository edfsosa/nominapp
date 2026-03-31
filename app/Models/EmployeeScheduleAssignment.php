<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Asignación de horario a un empleado, con vigencia por fechas (válida desde / hasta). */
class EmployeeScheduleAssignment extends Model
{
    protected $fillable = [
        'employee_id',
        'schedule_id',
        'valid_from',
        'valid_until',
        'notes',
        'created_by',
    ];

    /** Forzar formato Y-m-d para que SQLite almacene solo la fecha (sin tiempo). */
    protected $dateFormat = 'Y-m-d';

    protected $casts = [
        'valid_from'  => 'date',
        'valid_until' => 'date',
    ];

    /** Empleado al que pertenece esta asignación. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Horario asignado. */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /** Usuario que creó la asignación. */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Scope: asignaciones vigentes en una fecha dada (por defecto hoy).
     *
     * @param  Builder      $query
     * @param  Carbon|null  $date
     * @return Builder
     */
    public function scopeForDate(Builder $query, ?Carbon $date = null): Builder
    {
        $date ??= Carbon::today();

        return $query
            ->where('valid_from', '<=', $date)
            ->where(fn($q) => $q
                ->whereNull('valid_until')
                ->orWhere('valid_until', '>=', $date)
            );
    }

    /**
     * Scope: asignaciones activas hoy.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->forDate(Carbon::today());
    }
}
