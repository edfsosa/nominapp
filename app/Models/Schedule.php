<?php

namespace App\Models;

use App\Settings\PayrollSettings;
use Carbon\Carbon;
use App\Models\EmployeeScheduleAssignment;
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
        'shift_type',
    ];

    /**
     * Todos los días del horario (7 en total).
     *
     * @return HasMany
     */
    public function days(): HasMany
    {
        return $this->hasMany(ScheduleDay::class);
    }

    /**
     * Días laborales activos del horario (is_active = true), ordenados por día de semana.
     *
     * @return HasMany
     */
    public function activeDays(): HasMany
    {
        return $this->hasMany(ScheduleDay::class)
            ->where('is_active', true)
            ->orderBy('day_of_week');
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
     * Empleados asignados via campo legacy schedule_id.
     *
     * @return HasMany
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Empleados con asignación activa a este horario hoy (nuevo sistema).
     *
     * @return HasManyThrough
     */
    public function currentEmployees(): HasManyThrough
    {
        $today = Carbon::today()->toDateString();

        return $this->hasManyThrough(
            Employee::class,
            EmployeeScheduleAssignment::class,
            'schedule_id',  // FK en employee_schedule_assignments
            'id',           // FK en employees
            'id',           // PK en schedules
            'employee_id',  // PK local en employee_schedule_assignments
        )
            ->where('employee_schedule_assignments.valid_from', '<=', $today)
            ->where(fn($q) => $q
                ->whereNull('employee_schedule_assignments.valid_until')
                ->orWhere('employee_schedule_assignments.valid_until', '>=', $today)
            );
    }

    /**
     * Retorna true si el día de la semana dado es libre (inactivo) en este horario.
     *
     * @param  int  $dayOfWeek  1=Lunes … 7=Domingo
     * @return bool
     */
    public function isDayOff(int $dayOfWeek): bool
    {
        $day = $this->days()->where('day_of_week', $dayOfWeek)->first();
        return $day ? !$day->is_active : true;
    }

    public function getMonthlyHours(): float
    {
        $settings = app(PayrollSettings::class);
        return match ($this->shift_type) {
            'nocturno' => $settings->monthly_hours_nocturno,
            'mixto' => $settings->monthly_hours_mixto,
            default => $settings->monthly_hours,
        };
    }

    public function getDailyMaxHours(): float
    {
        $settings = app(PayrollSettings::class);
        return match ($this->shift_type) {
            'nocturno' => $settings->daily_hours_nocturno,
            'mixto' => $settings->daily_hours_mixto,
            default => $settings->daily_hours,
        };
    }

    public static function getShiftTypeOptions(): array
    {
        return [
            'diurno'   => 'Diurno (06:00 - 20:00)',
            'nocturno' => 'Nocturno (20:00 - 06:00)',
            'mixto'    => 'Mixto',
        ];
    }

    public static function getShiftTypeLabels(): array
    {
        return [
            'diurno'   => 'Diurno',
            'nocturno' => 'Nocturno',
            'mixto'    => 'Mixto',
        ];
    }

    public static function getShiftTypeColors(): array
    {
        return [
            'diurno'   => 'success',
            'nocturno' => 'info',
            'mixto'    => 'warning',
        ];
    }
}
