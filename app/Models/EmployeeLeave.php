<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Representa un permiso o licencia registrado para un empleado. */
class EmployeeLeave extends Model
{
    protected $fillable = [
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'reason',
        'document_path',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    /**
     * Relación con el empleado al que pertenece el permiso.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Opciones de tipo de permiso para selects y filtros.
     *
     * @return array<string, string>
     */
    public static function getTypeOptions(): array
    {
        return [
            'medical_leave'   => 'Reposo Médico',
            'vacation'        => 'Vacaciones',
            'day_off'         => 'Día Libre',
            'maternity_leave' => 'Permiso por Maternidad',
            'paternity_leave' => 'Permiso por Paternidad',
            'unpaid_leave'    => 'Sin Goce de Sueldo',
            'other'           => 'Otro',
        ];
    }

    /**
     * Colores de badge por tipo de permiso.
     *
     * @return array<string, string>
     */
    public static function getTypeColors(): array
    {
        return [
            'medical_leave'   => 'danger',
            'vacation'        => 'success',
            'day_off'         => 'info',
            'maternity_leave' => 'primary',
            'paternity_leave' => 'gray',
            'unpaid_leave'    => 'warning',
            'other'           => 'gray',
        ];
    }

    /**
     * Íconos por tipo de permiso.
     *
     * @return array<string, string>
     */
    public static function getTypeIcons(): array
    {
        return [
            'medical_leave'   => 'heroicon-o-heart',
            'vacation'        => 'heroicon-o-sun',
            'day_off'         => 'heroicon-o-calendar',
            'maternity_leave' => 'heroicon-o-home',
            'paternity_leave' => 'heroicon-o-home',
            'unpaid_leave'    => 'heroicon-o-pause-circle',
            'other'           => 'heroicon-o-document-text',
        ];
    }

    /**
     * Opciones de estado para selects y filtros.
     *
     * @return array<string, string>
     */
    public static function getStatusOptions(): array
    {
        return [
            'pending'  => 'Pendiente',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
        ];
    }

    /**
     * Colores de badge por estado.
     *
     * @return array<string, string>
     */
    public static function getStatusColors(): array
    {
        return [
            'pending'  => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
        ];
    }

    /**
     * Íconos por estado.
     *
     * @return array<string, string>
     */
    public static function getStatusIcons(): array
    {
        return [
            'pending'  => 'heroicon-o-clock',
            'approved' => 'heroicon-o-check-circle',
            'rejected' => 'heroicon-o-x-circle',
        ];
    }
}
