<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

class Aguinaldo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    /** @var array<int, string> Campos auditados en el historial de cambios. */
    protected array $auditInclude = [
        'status',
        'payment_method',
        'disbursement_batch_id',
        'paid_at',
    ];

    protected $fillable = [
        'aguinaldo_period_id',
        'employee_id',
        'total_earned',
        'months_worked',
        'aguinaldo_amount',
        'pdf_path',
        'generated_at',
        'status',
        'disbursement_batch_id',
        'payment_method',
        'paid_at',
    ];

    protected $casts = [
        'total_earned' => 'decimal:2',
        'months_worked' => 'decimal:2',
        'aguinaldo_amount' => 'decimal:2',
        'generated_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    // =========================================================================
    // RELACIONES
    // =========================================================================

    public function period(): BelongsTo
    {
        return $this->belongsTo(AguinaldoPeriod::class, 'aguinaldo_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AguinaldoItem::class);
    }

    /** Lote bancario al que pertenece este aguinaldo. */
    public function disbursementBatch(): BelongsTo
    {
        return $this->belongsTo(DisbursementBatch::class);
    }

    // =========================================================================
    // HELPERS ESTÁTICOS PARA ESTADOS
    // =========================================================================

    public static function getStatusOptions(): array
    {
        return [
            'pending' => 'Pendiente',
            'paid' => 'Pagado',
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
            'paid' => 'success',
            default => 'gray',
        };
    }

    public static function getStatusIcon(string $status): string
    {
        return match ($status) {
            'pending' => 'heroicon-o-clock',
            'paid' => 'heroicon-o-check-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    // =========================================================================
    // HELPERS ESTÁTICOS PARA MÉTODO DE PAGO
    // =========================================================================

    public static function getPaymentMethodOptions(): array
    {
        return [
            'transfer' => 'Acreditación bancaria',
            'cash' => 'Efectivo',
        ];
    }

    public static function getPaymentMethodLabel(string $method): string
    {
        return self::getPaymentMethodOptions()[$method] ?? $method;
    }

    public static function getPaymentMethodColor(string $method): string
    {
        return match ($method) {
            'transfer' => 'info',
            'cash' => 'success',
            default => 'gray',
        };
    }

    public static function getPaymentMethodIcon(string $method): string
    {
        return match ($method) {
            'transfer' => 'heroicon-o-building-library',
            'cash' => 'heroicon-o-banknotes',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    // =========================================================================
    // VERIFICADORES DE ESTADO
    // =========================================================================

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    // =========================================================================
    // ACCIONES
    // =========================================================================

    public function markAsPaid(): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function markAsPending(): void
    {
        $this->update([
            'status' => 'pending',
            'paid_at' => null,
        ]);
    }

    // =========================================================================
    // ATRIBUTOS COMPUTADOS
    // =========================================================================

    public function getTitleAttribute(): string
    {
        return "Aguinaldo {$this->period?->year} - {$this->employee?->full_name}";
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabel($this->status);
    }

    // =========================================================================
    // FORMATEO DE MONEDA
    // =========================================================================

    public static function formatCurrency(float|int|null $amount): string
    {
        if ($amount === null) {
            return 'Gs. 0';
        }

        return 'Gs. '.number_format($amount, 0, ',', '.');
    }

    public function getFormattedTotalEarnedAttribute(): string
    {
        return self::formatCurrency($this->total_earned);
    }

    public function getFormattedAguinaldoAmountAttribute(): string
    {
        return self::formatCurrency($this->aguinaldo_amount);
    }

    /**
     * Renderiza los valores de un registro de auditoría como HTML legible para el RelationManager.
     *
     * @param  string  $column  'old_values' o 'new_values'
     * @param  mixed  $auditRecord  Instancia del audit
     */
    public function formatAuditFieldsForPresentation(string $column, mixed $auditRecord): HtmlString
    {
        $values = $auditRecord->{$column} ?? [];
        if (empty($values)) {
            return new HtmlString('<span class="text-gray-400 text-xs">—</span>');
        }

        $fieldLabels = [
            'status' => 'Estado',
            'payment_method' => 'Método de pago',
            'disbursement_batch_id' => 'Lote bancario',
            'paid_at' => 'Fecha de pago',
        ];

        $html = '<ul class="space-y-0.5 text-sm">';
        foreach ($values as $key => $value) {
            $label = $fieldLabels[$key] ?? Str::headline($key);
            $formatted = $this->formatAuditValue($key, $value);
            $html .= "<li><span class=\"text-gray-500\">{$label}:</span> <span class=\"font-medium\">{$formatted}</span></li>";
        }
        $html .= '</ul>';

        return new HtmlString($html);
    }

    /** Formatea un valor individual del audit a texto legible. */
    private function formatAuditValue(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($key) {
            'status' => static::getStatusLabel($value),
            'payment_method' => static::getPaymentMethodLabel($value),
            'disbursement_batch_id' => "Lote #{$value}",
            'paid_at' => \Carbon\Carbon::parse($value)->format('d/m/Y H:i'),
            default => (string) $value,
        };
    }
}
