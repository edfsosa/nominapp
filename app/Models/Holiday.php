<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    // Campos asignables
    protected $fillable = [
        'date',
        'name',
    ];

    // Casts para atributos
    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Verificar si una fecha es feriado
     */
    public static function isHoliday(\DateTimeInterface|string $date): bool
    {
        return static::where('date', $date)->exists();
    }

    /**
     * Obtener feriado por fecha
     *
     * @return Holiday|null
     */
    public static function forDate(\DateTimeInterface|string $date)
    {
        return static::where('date', $date)->first();
    }
}
