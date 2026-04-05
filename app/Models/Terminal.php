<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Representa un dispositivo físico de marcación de asistencia en una sucursal. */
class Terminal extends Model
{
    protected $fillable = [
        'name',
        'code',
        'branch_id',
        'status',
        'device_brand',
        'device_model',
        'device_serial',
        'device_mac',
        'device_notes',
        'installed_at',
        'installed_by_id',
        'last_seen_at',
    ];

    protected $casts = [
        'installed_at' => 'date',
        'last_seen_at' => 'datetime',
    ];

    // =========================================================================
    // BOOT
    // =========================================================================

    /** Genera el código único automáticamente al crear la terminal. */
    protected static function booted(): void
    {
        static::creating(function (Terminal $terminal) {
            if (empty($terminal->code)) {
                $terminal->code = static::generateUniqueCode();
            }
        });
    }

    // =========================================================================
    // RELACIONES
    // =========================================================================

    /** Sucursal a la que pertenece esta terminal. */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Usuario que instaló la terminal. */
    public function installedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by_id');
    }

    /** Eventos de asistencia registrados desde esta terminal. */
    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }

    // =========================================================================
    // HELPERS ESTÁTICOS — LABELS, COLORES, OPCIONES
    // =========================================================================

    /**
     * Opciones de estado para Select en formularios.
     *
     * @return array<string, string>
     */
    public static function getStatusOptions(): array
    {
        return [
            'active'   => 'Activa',
            'inactive' => 'Inactiva',
        ];
    }

    /**
     * Labels cortos para badges y columnas.
     *
     * @return array<string, string>
     */
    public static function getStatusLabels(): array
    {
        return [
            'active'   => 'Activa',
            'inactive' => 'Inactiva',
        ];
    }

    /**
     * Colores semánticos para badges de Filament.
     *
     * @return array<string, string>
     */
    public static function getStatusColors(): array
    {
        return [
            'active'   => 'success',
            'inactive' => 'danger',
        ];
    }

    // =========================================================================
    // VERIFICADORES DE ESTADO
    // =========================================================================

    /** Indica si la terminal está activa. */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Indica si la terminal está inactiva. */
    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    // =========================================================================
    // HELPERS DE INSTANCIA
    // =========================================================================

    /**
     * Retorna la URL pública de la terminal.
     *
     * @return string
     */
    public function getUrlAttribute(): string
    {
        return route('terminal.show', $this->code);
    }

    /**
     * Descripción del dispositivo (marca + modelo).
     *
     * @return string|null
     */
    public function getDeviceDescriptionAttribute(): ?string
    {
        $parts = array_filter([$this->device_brand, $this->device_model]);
        return $parts ? implode(' ', $parts) : null;
    }

    // =========================================================================
    // HELPERS ESTÁTICOS
    // =========================================================================

    /**
     * Genera un código alfanumérico único de 8 caracteres para la terminal.
     *
     * @return string
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = strtolower(Str::random(8));
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
