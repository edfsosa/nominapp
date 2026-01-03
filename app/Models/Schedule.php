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
     * Empleados asignados a este horario
     * @return HasMany
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function isDayOff($dayOfWeek)
    {
        // Verifica si el empleado tiene un horario asignado
        $day = $this->days()->where('day_of_week', $dayOfWeek)->first();
        return $day ? $day->is_day_off : true; // Si no hay día definido, se considera día libre
    }
}
