<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Schedule extends Model
{
    protected $table = 'schedules';

    // Campos asignables
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Días asociados a este horario
     * @return HasMany
     */
    public function days(): HasMany
    {
        return $this->hasMany(ScheduleDay::class);
    }

    /**
     * Descansos a través de los días
     * @return HasManyThrough
     */
    public function breaks(): HasManyThrough
    {
        return $this->hasManyThrough(
            ScheduleBreak::class,
            ScheduleDay::class,
            'schedule_id',        // FK en schedule_days
            'schedule_day_id',     // FK en schedule_breaks
            'id',                  // PK en schedules
            'id'                   // PK en schedule_days
        );
    }

    /**
     * Empleados asignados a este horario (pivot con vigencia)
     * @return BelongsToMany
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(
            Employee::class,
            'employee_schedule',
            'schedule_id',
            'employee_id'
        )
            ->withPivot('effective_from', 'effective_to')
            ->withTimestamps();
    }
}
