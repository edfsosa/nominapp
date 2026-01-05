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
        'is_extraordinary_work',
        'is_holiday',
        'manual_adjustment',
        'overtime_approved',
        'on_vacation',
        'justified_absence',
        'is_calculated',
        'calculated_at',
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
        'is_extraordinary_work' => 'boolean',
        'is_holiday' => 'boolean',
        'manual_adjustment' => 'boolean',
        'overtime_approved' => 'boolean',
        'on_vacation' => 'boolean',
        'justified_absence' => 'boolean',
        'is_calculated' => 'boolean',
        'calculated_at' => 'datetime',
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
        return self::getStatusLabel($this->status);
    }

    /**
     * Obtiene el label traducido del estado
     */
    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'present' => 'Presente',
            'absent' => 'Ausente',
            'on_leave' => 'De permiso',
            'holiday' => 'Feriado',
            'weekend' => 'Fin de semana',
            default => 'Desconocido',
        };
    }

    /**
     * Obtiene el color del badge según el estado
     */
    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'present' => 'success',
            'absent' => 'danger',
            'on_leave' => 'warning',
            'holiday' => 'info',
            'weekend' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Obtiene el icono según el estado
     */
    public static function getStatusIcon(string $status): string
    {
        return match ($status) {
            'present' => 'heroicon-o-check-circle',
            'absent' => 'heroicon-o-x-circle',
            'on_leave' => 'heroicon-o-document-text',
            'holiday' => 'heroicon-o-gift',
            'weekend' => 'heroicon-o-calendar',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Obtiene todas las opciones de estado para selects
     */
    public static function getStatusOptions(): array
    {
        return [
            'present' => 'Presente',
            'absent' => 'Ausente',
            'on_leave' => 'De permiso',
            'holiday' => 'Feriado',
            'weekend' => 'Fin de semana',
        ];
    }

    /**
     * Formatea un valor booleano para mostrar Sí/No
     */
    public static function formatBoolean(bool $value): string
    {
        return $value ? 'Sí' : 'No';
    }

    /**
     * Obtiene el color para un badge booleano
     */
    public static function getBooleanColor(bool $value, string $trueColor = 'success', string $falseColor = 'gray'): string
    {
        return $value ? $trueColor : $falseColor;
    }

    /**
     * Obtiene el mensaje de status después de calcular/recalcular
     */
    public function getStatusMessage(bool $wasCalculated): string
    {
        $action = $wasCalculated ? 'recalculado' : 'calculado';

        return match ($this->status) {
            'present' => "✓ Empleado presente - Cálculos {$action}s",
            'absent' => "⚠ Empleado ausente",
            'on_leave' => "📋 Empleado con permiso/vacaciones",
            'holiday' => "🎉 Día feriado",
            'weekend' => "📅 Fin de semana",
            default => "Cálculo {$action}",
        };
    }
}
