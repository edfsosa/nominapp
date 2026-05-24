<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Departamento administrativo del Paraguay. */
class PyDepartment extends Model
{
    protected $fillable = ['id', 'name', 'capital'];

    public function cities(): HasMany
    {
        return $this->hasMany(PyCity::class);
    }

    /** @return array<int|string, string> */
    public static function getOptions(): array
    {
        return static::orderBy('name')->pluck('name', 'id')->toArray();
    }
}
