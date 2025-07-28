<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
