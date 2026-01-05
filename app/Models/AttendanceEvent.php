<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEvent extends Model
{
    /** @use HasFactory<\Database\Factories\AttendanceFactory> */
    use HasFactory;

    protected $fillable = [
        'attendance_day_id',
        'event_type',
        'location',
        'recorded_at',
        'employee_id',
        'employee_name',
        'employee_ci',
        'branch_id',
        'branch_name',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'location' => 'array',
    ];

    /**
     * Relación con AttendanceDay
     */
    public function day(): BelongsTo
    {
        return $this->belongsTo(AttendanceDay::class, 'attendance_day_id');
    }

    /**
     * Relación directa con Employee (datos desnormalizados)
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relación directa con Branch (datos desnormalizados)
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Accessor optimizado para mostrar ubicación formateada
     * Cache del resultado para evitar procesamiento repetido
     */
    public function getLocationDisplayAttribute(): string
    {
        if (!$this->location) {
            return 'Sin ubicación';
        }

        try {
            if (isset($this->location['lat']) && isset($this->location['lng'])) {
                return sprintf(
                    'Lat: %s, Lng: %s',
                    number_format($this->location['lat'], 6, '.', ''),
                    number_format($this->location['lng'], 6, '.', '')
                );
            }

            if (is_array($this->location)) {
                return collect($this->location)
                    ->map(fn($value, $key) => "{$key}: {$value}")
                    ->join(', ');
            }

            return 'Ubicación inválida';
        } catch (\Exception $e) {
            return 'Error al procesar ubicación';
        }
    }

    /**
     * Accessor optimizado para URL de Google Maps
     */
    public function getGoogleMapsUrlAttribute(): ?string
    {
        if (!$this->location || !is_array($this->location)) {
            return null;
        }

        if (isset($this->location['lat']) && isset($this->location['lng'])) {
            $lat = (float) $this->location['lat'];
            $lng = (float) $this->location['lng'];

            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                return sprintf(
                    'https://www.google.com/maps?q=%s,%s',
                    $lat,
                    $lng
                );
            }
        }

        return null;
    }
}
