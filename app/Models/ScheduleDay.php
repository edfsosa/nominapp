<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleDay extends Model
{
    protected $fillable = [
        'schedule_id',
        'day_of_week',
        'start_time',
        'end_time'
    ];

    // Relación con el horario al que pertenece este día
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    // Relación con los descansos de este día
    public function breaks(): HasMany
    {
        return $this->hasMany(ScheduleBreak::class);
    }

    // Accesor para obtener el nombre del día de la semana
    public function getDayOfWeekNameAttribute(): string
    {
        $days = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo'
        ];

        return $days[$this->day_of_week] ?? 'Desconocido';
    }

    // Calcular las horas programadas para este día
    public function getScheduledHoursAttribute(): float
    {
        // Calcular la duración total entre start_time y end_time
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        // Ajustar si el horario cruza la medianoche
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        // Restar los minutos de descanso programados
        $totalMinutes = $start->diffInMinutes($end) - $this->total_break_minutes;

        // Retornar las horas programadas redondeadas a 2 decimales
        return round($totalMinutes / 60, 2);
    }

    // Calcular los minutos totales de descanso programados para este día
    public function getTotalBreakMinutesAttribute(): int
    {
        return $this->breaks->sum(function ($break) {
            return $break->duration_in_minutes;
        });
    }
}
