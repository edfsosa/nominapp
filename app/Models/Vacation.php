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
        'payment_method',
        'reason',
        'status',
        'business_days',
        'payment_amount',
        'payment_status',
        'paid_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'return_date' => 'date',
        'business_days' => 'integer',
        'payment_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Relación con el balance de vacaciones. */
    public function vacationBalance(): BelongsTo
    {
        return $this->belongsTo(VacationBalance::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /** Verifica si la remuneración vacacional ya fue pagada. */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Verifica si el pago vacacional está vencido (ley exige pago antes del inicio, Art. 218 CLT).
     *
     * @param  int  $daysThreshold  Días de anticipación mínimos para alertar.
     */
    public function isPaymentOverdue(int $daysThreshold = 0): bool
    {
        if ($this->isPaid()) {
            return false;
        }

        return now()->greaterThanOrEqualTo($this->start_date->subDays($daysThreshold));
    }

    // ─── Opciones estáticas ──────────────────────────────────────────────────

    /** @return array<string, string> */
    public static function getPaymentMethodOptions(): array
    {
        return [
            'immediate' => 'Pago adelantado',
            'with_payroll' => 'Con próximo salario',
        ];
    }

    public static function getPaymentMethodLabel(string $method): string
    {
        return self::getPaymentMethodOptions()[$method] ?? $method;
    }

    public static function getPaymentMethodColor(string $method): string
    {
        return match ($method) {
            'immediate' => 'success',
            'with_payroll' => 'info',
            default => 'gray',
        };
    }

    public static function getPaymentMethodIcon(string $method): string
    {
        return match ($method) {
            'immediate' => 'heroicon-o-banknotes',
            'with_payroll' => 'heroicon-o-queue-list',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /** @return array<string, string> */
    public static function getStatusOptions(): array
    {
        return [
            'pending' => 'Pendiente',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
        ];
    }

    public static function getStatusLabel(string $status): string
    {
        return self::getStatusOptions()[$status] ?? $status;
    }

    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            default => 'gray',
        };
    }

    public static function getStatusIcon(string $status): string
    {
        return match ($status) {
            'pending' => 'heroicon-o-clock',
            'approved' => 'heroicon-o-check-circle',
            'rejected' => 'heroicon-o-x-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    // ─── Atributos calculados ─────────────────────────────────────────────────

    public function getTotalDaysAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function getDaysDescriptionAttribute(): string
    {
        $days = $this->total_days;

        return $days.' '.($days === 1 ? 'día' : 'días');
    }

    public function getPeriodFormattedAttribute(): string
    {
        return $this->start_date->format('d/m/Y').' → '.$this->end_date->format('d/m/Y');
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::getPaymentMethodLabel($this->payment_method ?? 'immediate');
    }

    public function getPaymentMethodColorAttribute(): string
    {
        return self::getPaymentMethodColor($this->payment_method ?? 'immediate');
    }

    public function getPaymentMethodIconAttribute(): string
    {
        return self::getPaymentMethodIcon($this->payment_method ?? 'immediate');
    }

    public function getStatusColorAttribute(): string
    {
        return self::getStatusColor($this->status);
    }

    public function getStatusIconAttribute(): string
    {
        return self::getStatusIcon($this->status);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabel($this->status);
    }

    public function getBusinessDaysDescriptionAttribute(): string
    {
        $days = $this->business_days ?? $this->total_days;

        return $days.' '.($days === 1 ? 'día hábil' : 'días hábiles');
    }

    public function getReturnDateFormattedAttribute(): ?string
    {
        return $this->return_date?->format('d/m/Y');
    }

    public function getCreatedAtDescriptionAttribute(): string
    {
        return $this->created_at->format('d/m/Y H:i');
    }

    public function getCreatedAtSinceAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    public function getUpdatedAtDescriptionAttribute(): string
    {
        return $this->updated_at->format('d/m/Y H:i');
    }

    public function getUpdatedAtSinceAttribute(): string
    {
        return $this->updated_at->diffForHumans();
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeForYear($query, int $year)
    {
        return $query->whereYear('start_date', $year);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
