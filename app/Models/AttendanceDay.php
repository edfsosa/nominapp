<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceDay extends Model
{
    protected $fillable = [
        'employee_id',
        'date',
        'status',
        'total_hours',
        'net_hours',
        'expected_hours',
        'expected_check_in',
        'expected_check_out',
        'expected_break_minutes',
        'late_minutes',
        'early_leave_minutes',
        'extra_hours',
        'break_minutes',
        'check_in_time',
        'check_out_time',
        'anomaly_flag',
        'notes',
        'is_weekend',
        'is_holiday',
        'manual_adjustment',
        'overtime_approved',
        'on_vacation',
        'justified_absence',
    ];

    protected $casts = [
        'date' => 'date',
        'total_hours' => 'decimal:2',
        'net_hours' => 'decimal:2',
        'expected_hours' => 'decimal:2',
        'expected_break_minutes' => 'integer',
        'late_minutes' => 'integer',
        'early_leave_minutes' => 'integer',
        'extra_hours' => 'decimal:2',
        'break_minutes' => 'integer',
        'anomaly_flag' => 'boolean',
        'is_weekend' => 'boolean',
        'is_holiday' => 'boolean',
        'manual_adjustment' => 'boolean',
        'overtime_approved' => 'boolean',
        'on_vacation' => 'boolean',
        'justified_absence' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }

    public function getDateFormattedAttribute(): string
    {
        return Carbon::parse($this->date)->format('d/m/Y');
    }

    public function getStatusInSpanishAttribute(): string
    {
        switch ($this->status) {
            case 'present':
                return 'Presente';
            case 'absent':
                return 'Ausente';
            case 'on_leave':
                return 'De permiso';
            case 'holiday':
                return 'Festivo';
            default:
                return 'Desconocido';
        }
    }
}
