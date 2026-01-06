<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vacation extends Model
{
    /** @use HasFactory<\Database\Factories\VacationFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'type',
        'reason',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Obtiene las opciones de tipo de vacación para filtros y selects
     */
    public static function getTypeOptions(): array
    {
        return [
            'paid' => 'Remunerada',
            'unpaid' => 'No Remunerada',
        ];
    }

    /**
     * Obtiene las opciones de estado para filtros y selects
     */
    public static function getStatusOptions(): array
    {
        return [
            'pending' => 'Pendiente',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
        ];
    }

    /**
     * Calcula los días totales de vacaciones
     */
    public function getTotalDaysAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Obtiene la descripción de días (ej: "5 días")
     */
    public function getDaysDescriptionAttribute(): string
    {
        $days = $this->total_days;
        return $days . ' ' . ($days === 1 ? 'día' : 'días');
    }

    /**
     * Obtiene el período formateado
     */
    public function getPeriodFormattedAttribute(): string
    {
        return $this->start_date->format('d/m/Y') . ' → ' . $this->end_date->format('d/m/Y');
    }

    /**
     * Obtiene el color del badge para el tipo de vacación
     */
    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'paid' => 'success',
            'unpaid' => 'warning',
            default => 'gray',
        };
    }

    /**
     * Obtiene el ícono para el tipo de vacación
     */
    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            'paid' => 'heroicon-o-currency-dollar',
            'unpaid' => 'heroicon-o-minus-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Obtiene la etiqueta formateada del tipo de vacación
     */
    public function getTypeLabelAttribute(): string
    {
        return self::getTypeOptions()[$this->type] ?? $this->type;
    }

    /**
     * Obtiene el color del badge para el estado
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Obtiene el ícono para el estado
     */
    public function getStatusIconAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'heroicon-o-clock',
            'approved' => 'heroicon-o-check-circle',
            'rejected' => 'heroicon-o-x-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Obtiene la etiqueta formateada del estado
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusOptions()[$this->status] ?? $this->status;
    }

    /**
     * Obtiene la fecha de creación formateada
     */
    public function getCreatedAtDescriptionAttribute(): string
    {
        return $this->created_at->format('d/m/Y H:i');
    }

    /**
     * Obtiene la fecha de creación en formato "hace X tiempo"
     */
    public function getCreatedAtSinceAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Obtiene la fecha de actualización formateada
     */
    public function getUpdatedAtDescriptionAttribute(): string
    {
        return $this->updated_at->format('d/m/Y H:i');
    }

    /**
     * Obtiene la fecha de actualización en formato "hace X tiempo"
     */
    public function getUpdatedAtSinceAttribute(): string
    {
        return $this->updated_at->diffForHumans();
    }
}
