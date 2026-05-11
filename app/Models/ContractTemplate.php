<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Plantilla de cuerpo/cláusulas por tipo de contrato. Una por cada tipo. */
class ContractTemplate extends Model
{
    protected $fillable = ['type', 'body'];

    /**
     * Retorna la plantilla para el tipo de contrato dado, o null si no existe.
     */
    public static function getForType(string $type): ?static
    {
        return static::where('type', $type)->first();
    }
}
