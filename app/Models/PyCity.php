<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ciudad o municipio del Paraguay. */
class PyCity extends Model
{
    protected $fillable = ['id', 'py_department_id', 'name', 'population'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(PyDepartment::class, 'py_department_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(EmployeeAddress::class);
    }

    /** @return array<int|string, string> */
    public static function getOptions(?int $departmentId = null): array
    {
        return static::when($departmentId, fn ($q) => $q->where('py_department_id', $departmentId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
}
