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
        'vacation_balance_id',
        'start_date',
        'end_date',
        'return_date',
        'type',
        'reason',
        'status',
        'business_days',
        'payment_amount',
        'payment_status',
        'paid_at',
    ];

    protected $casts = [
        'start_date'     => 'date',
        'end_date'       => 'date',
        'return_date'    => 'date',
        'business_days'  => 'integer',
        'payment_amount' => 'decimal:2',
        'paid_at'        => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relación con el balance de vacaciones
     */
    public function vacationBalance(): BelongsTo
    {
        return $this->belongsTo(VacationBalance::class);
    }

    /**
     * Verifica si está pendiente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Verifica si está aprobada
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Verifica si está rechazada
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Verifica si la remuneración vacacional ya fue pagada.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Verifica si el pago está pendiente y la vacación ya comenzó o está por comenzar.
     * La ley exige pagar antes del inicio (Art. 218 CLT).
     *
     * @param  int $daysThreshold  Días de anticipación mínimos para alertar (default: 0 = ya debería estar pagado)
     */
    public function isPaymentOverdue(int $daysThreshold = 0): bool
    {
        if ($this->type !== 'paid' || $this->isPaid()) {
            return false;
        }

        return now()->greaterThanOrEqualTo($this->start_date->subDays($daysThreshold));
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
     * Obtiene la etiqueta del tipo de vacación
     */
    public static function getTypeLabel(string $type): string
    {
        return self::getTypeOptions()[$type] ?? $type;
    }

    /**
     * Obtiene el color del tipo de vacación
     */
    public static function getTypeColor(string $type): string
    {
        return match ($type) {
            'paid' => 'success',
            'unpaid' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Obtiene la etiqueta del estado
     */
    public static function getStatusLabel(string $status): string
    {
        return self::getStatusOptions()[$status] ?? $status;
    }

    /**
     * Obtiene el color del estado
     */
    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Obtiene el ícono del estado
     */
    public static function getStatusIcon(string $status): string
    {
        return match ($status) {
            'pending' => 'heroicon-o-clock',
            'approved' => 'heroicon-o-check-circle',
            'rejected' => 'heroicon-o-x-circle',
            default => 'heroicon-o-question-mark-circle',
        };
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

    /**
     * Obtiene la descripción de días hábiles
     */
    public function getBusinessDaysDescriptionAttribute(): string
    {
        $days = $this->business_days ?? $this->total_days;
        return $days . ' ' . ($days === 1 ? 'día hábil' : 'días hábiles');
    }

    /**
     * Obtiene la fecha de reintegro formateada
     */
    public function getReturnDateFormattedAttribute(): ?string
    {
        return $this->return_date?->format('d/m/Y');
    }

    /**
     * Scope para vacaciones pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope para vacaciones aprobadas
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope para vacaciones de un año específico
     */
    public function scopeForYear($query, int $year)
    {
        return $query->whereYear('start_date', $year);
    }

    /**
     * Scope para vacaciones de un empleado
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
