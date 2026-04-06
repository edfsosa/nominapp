<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Override puntual del turno planificado de un empleado para una fecha específica. */
class ShiftOverride extends Model
{
    protected $fillable = [
        'employee_id',
        'override_date',
        'shift_id',
        'reason_type',
        'notes',
        'created_by_id',
    ];

    protected $casts = [
        'override_date' => 'date',
    ];

    /** Empleado afectado por el override. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Turno asignado para ese día específico. */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class, 'shift_id');
    }

    /** Usuario que autorizó el cambio. */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
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

    /** @return array<string, string> */
    public static function getReasonTypeColors(): array
    {
        return [
            'cambio_turno'  => 'info',
            'guardia_extra' => 'warning',
            'permiso'       => 'success',
            'reposo'        => 'danger',
            'otro'          => 'gray',
        ];
    }
}
