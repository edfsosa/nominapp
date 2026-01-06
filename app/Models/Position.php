<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $fillable = [
        'name',
        'department_id',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Obtiene las opciones de cargos con sus departamentos para selects
     */
    public static function getOptionsWithDepartment(): array
    {
        return static::with('department')
            ->get()
            ->mapWithKeys(function ($position) {
                $label = $position->name;
                if ($position->department) {
                    $label .= ' - ' . $position->department->name;
                }
                return [$position->id => $label];
            })
            ->toArray();
    }
}
