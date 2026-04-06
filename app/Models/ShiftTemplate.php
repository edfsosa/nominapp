<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Turno de trabajo reutilizable dentro de un patrón de rotación. */
class ShiftTemplate extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'color',
        'shift_type',
        'is_day_off',
        'start_time',
        'end_time',
        'break_minutes',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_day_off'     => 'boolean',
        'is_active'      => 'boolean',
        'break_minutes'  => 'integer',
    ];

    /** Empresa a la que pertenece el turno. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Overrides que usan este turno. */
    public function overrides(): HasMany
    {
        return $this->hasMany(ShiftOverride::class, 'shift_id');
    }

    /**
     * Retorna true si el turno cruza la medianoche (ej: 22:00 → 06:00).
     */
    public function crossesMidnight(): bool
    {
        if ($this->is_day_off || ! $this->start_time || ! $this->end_time) {
            return false;
        }

        return $this->end_time < $this->start_time;
    }

    /**
     * Duración neta del turno en minutos (descontando break_minutes).
     * Retorna 0 si es franco.
     */
    public function netMinutes(): int
    {
        if ($this->is_day_off || ! $this->start_time || ! $this->end_time) {
            return 0;
        }

        $start  = \Carbon\Carbon::createFromTimeString($this->start_time);
        $end    = \Carbon\Carbon::createFromTimeString($this->end_time);

        if ($end->lte($start)) {
            $end->addDay(); // turno cruza medianoche
        }

        return (int) $start->diffInMinutes($end) - $this->break_minutes;
    }

    // ──────────────────────────────────────────
    // Helpers estáticos para labels y colores
    // ──────────────────────────────────────────

    /** @return array<string, string> */
    public static function getShiftTypeOptions(): array
    {
        return [
            'diurno'   => 'Diurno (06:00 - 20:00)',
            'nocturno' => 'Nocturno (20:00 - 06:00)',
            'mixto'    => 'Mixto',
        ];
    }

    /** @return array<string, string> */
    public static function getShiftTypeLabels(): array
    {
        return [
            'diurno'   => 'Diurno',
            'nocturno' => 'Nocturno',
            'mixto'    => 'Mixto',
        ];
    }

    /** @return array<string, string> */
    public static function getShiftTypeColors(): array
    {
        return [
            'diurno'   => 'success',
            'nocturno' => 'info',
            'mixto'    => 'warning',
        ];
    }

    /** @return array<string, string> */
    public static function getReasonTypeLabels(): array
    {
        return [
            'cambio_turno'  => 'Cambio de turno',
            'guardia_extra' => 'Guardia extra',
            'permiso'       => 'Permiso',
            'reposo'        => 'Reposo médico',
            'otro'          => 'Otro',
        ];
    }
}
