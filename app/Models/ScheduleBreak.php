<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ScheduleBreak extends Model
{
    protected $fillable = [
        'schedule_day_id',
        'name',
        'start_time',
        'end_time'
    ];

    public function day(): BelongsTo
    {
        return $this->belongsTo(ScheduleDay::class, 'schedule_day_id');
    }

    public function getDurationInMinutesAttribute(): int
    {
        // Calcular la duración del descanso en minutos
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        // Ajustar si el descanso cruza la medianoche
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        // Retornar la diferencia en minutos 
        return $start->diffInMinutes($end);
    }
}
