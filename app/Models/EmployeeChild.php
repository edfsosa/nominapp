<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Representa un hijo registrado a cargo de un empleado.
 * Usado para calcular la bonificación familiar (Arts. 253-262 CLT).
 */
class EmployeeChild extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'first_name',
        'last_name',
        'birth_date',
        'ci',
        'birth_certificate_path',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    /** Empleado al que pertenece este hijo. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Nombre completo del hijo. */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /** Años cumplidos del hijo. */
    public function getAgeAttribute(): int
    {
        return $this->birth_date->age;
    }

    /**
     * Indica si el hijo es menor de 18 años y por tanto elegible
     * para la bonificación familiar.
     */
    public function getIsEligibleAttribute(): bool
    {
        return $this->birth_date->gt(now()->subYears(18));
    }

    /** URL pública del certificado de nacimiento adjunto, si existe. */
    public function getBirthCertificateUrlAttribute(): ?string
    {
        return $this->birth_certificate_path
            ? Storage::disk('public')->url($this->birth_certificate_path)
            : null;
    }
}
