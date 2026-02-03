<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $fillable = [
        'name',
        'department_id',
        'parent_id',
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
     * Cargo padre al que reporta este cargo.
     */
    public function parent()
    {
        return $this->belongsTo(Position::class, 'parent_id');
    }

    /**
     * Cargos hijos que reportan a este cargo.
     */
    public function children()
    {
        return $this->hasMany(Position::class, 'parent_id');
    }

    /**
     * Obtiene todos los descendientes de forma recursiva.
     */
    public function getAllDescendants(): \Illuminate\Support\Collection
    {
        $descendants = collect();

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getAllDescendants());
        }

        return $descendants;
    }

    /**
     * Obtiene todos los IDs de descendientes (para prevenir ciclos).
     */
    public function getAllDescendantIds(): array
    {
        return $this->getAllDescendants()->pluck('id')->toArray();
    }

    /**
     * Verifica si este cargo es raíz (sin padre).
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Obtiene el nivel jerárquico del cargo (0 = raíz).
     */
    public function getHierarchyLevel(): int
    {
        $level = 0;
        $current = $this;

        while ($current->parent) {
            $level++;
            $current = $current->parent;
        }

        return $level;
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
