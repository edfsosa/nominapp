<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Patrón de rotación: ciclo ordenado de turnos definido como secuencia JSON de IDs. */
class RotationPattern extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'sequence',
        'is_active',
    ];

    protected $casts = [
        'sequence'  => 'array',
        'is_active' => 'boolean',
    ];

    /** Empresa propietaria del patrón. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Asignaciones de empleados que usan este patrón. */
    public function assignments(): HasMany
    {
        return $this->hasMany(RotationAssignment::class, 'pattern_id');
    }

    /**
     * Largo del ciclo en días (derivado del array sequence).
     */
    public function getCycleLengthAttribute(): int
    {
        return count($this->sequence ?? []);
    }

    /**
     * Retorna el ShiftTemplate ID para la posición dada del ciclo.
     *
     * @param  int  $position  Posición 0-based dentro del ciclo.
     * @return int|null
     */
    public function shiftIdAtPosition(int $position): ?int
    {
        $seq = $this->sequence ?? [];

        if (empty($seq)) {
            return null;
        }

        return $seq[$position % count($seq)] ?? null;
    }
}
