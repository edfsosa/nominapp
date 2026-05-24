<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dirección postal de un empleado. */
class EmployeeAddress extends Model
{
    protected $fillable = [
        'employee_id',
        'type',
        'street',
        'neighborhood',
        'py_city_id',
        'notes',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(PyCity::class, 'py_city_id');
    }

    /** @return array<string, string> */
    public static function getTypeOptions(): array
    {
        return [
            'principal' => 'Principal (domicilio)',
            'laboral' => 'Laboral',
            'emergencia' => 'Emergencia',
            'otro' => 'Otro',
        ];
    }

    /** @return array<string, string> */
    public static function getTypeLabels(): array
    {
        return [
            'principal' => 'Principal',
            'laboral' => 'Laboral',
            'emergencia' => 'Emergencia',
            'otro' => 'Otro',
        ];
    }

    /** @return array<string, string> */
    public static function getTypeColors(): array
    {
        return [
            'principal' => 'success',
            'laboral' => 'info',
            'emergencia' => 'warning',
            'otro' => 'gray',
        ];
    }

    /** @return array<string, string> */
    public static function getTypeIcons(): array
    {
        return [
            'principal' => 'heroicon-o-home',
            'laboral' => 'heroicon-o-building-office',
            'emergencia' => 'heroicon-o-exclamation-triangle',
            'otro' => 'heroicon-o-map-pin',
        ];
    }
}
