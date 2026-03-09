<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class Liquidacion extends Model
{
    protected $table = 'liquidaciones';

    protected $fillable = [
        'employee_id',
        'termination_date',
        'termination_type',
        'termination_reason',
        'preaviso_otorgado',
        'hire_date',
        'base_salary',
        'daily_salary',
        'years_of_service',
        'months_of_service',
        'days_of_service',
        'average_salary_6m',
        'preaviso_days',
        'preaviso_amount',
        'indemnizacion_amount',
        'vacaciones_days',
        'vacaciones_amount',
        'aguinaldo_proporcional_amount',
        'salario_pendiente_days',
        'salario_pendiente_amount',
        'ips_deduction',
        'loan_deduction',
        'other_deductions',
        'total_haberes',
        'total_deductions',
        'net_amount',
        'status',
        'pdf_path',
        'calculated_at',
        'closed_at',
        'created_by_id',
        'notes',
    ];

    protected $casts = [
        'termination_date' => 'date',
        'hire_date' => 'date',
        'preaviso_otorgado' => 'boolean',
        'base_salary' => 'decimal:2',
        'daily_salary' => 'decimal:2',
        'years_of_service' => 'integer',
        'months_of_service' => 'integer',
        'days_of_service' => 'integer',
        'average_salary_6m' => 'decimal:2',
        'preaviso_days' => 'integer',
        'preaviso_amount' => 'decimal:2',
        'indemnizacion_amount' => 'decimal:2',
        'vacaciones_days' => 'integer',
        'vacaciones_amount' => 'decimal:2',
        'aguinaldo_proporcional_amount' => 'decimal:2',
        'salario_pendiente_days' => 'integer',
        'salario_pendiente_amount' => 'decimal:2',
        'ips_deduction' => 'decimal:2',
        'loan_deduction' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_haberes' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'calculated_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LiquidacionItem::class);
    }

    public static function getTerminationTypeOptions(): array
    {
        return [
            'unjustified_dismissal' => 'Despido Injustificado',
            'justified_dismissal'   => 'Despido Justificado',
            'resignation'           => 'Renuncia Voluntaria',
            'mutual_agreement'      => 'Mutuo Acuerdo',
            'contract_end'          => 'Fin de Contrato',
        ];
    }

    public static function getTerminationTypeLabel(string $type): string
    {
        return self::getTerminationTypeOptions()[$type] ?? $type;
    }

    public static function includesPreaviso(string $type): bool
    {
        return in_array($type, ['unjustified_dismissal', 'justified_dismissal']);
    }

    public static function includesIndemnizacion(string $type): bool
    {
        return $type === 'unjustified_dismissal';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isCalculated(): bool
    {
        return $this->status === 'calculated';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public static function formatCurrency(float|int|null $amount): string
    {
        if ($amount === null) {
            return 'Gs. 0';
        }

        return 'Gs. ' . number_format($amount, 0, ',', '.');
    }

    public function getFormattedNetAmountAttribute(): string
    {
        return self::formatCurrency($this->net_amount);
    }

    public function getFormattedTotalHaberesAttribute(): string
    {
        return self::formatCurrency($this->total_haberes);
    }

    public function getFormattedTotalDeductionsAttribute(): string
    {
        return self::formatCurrency($this->total_deductions);
    }

    public function recalculateTotals(): void
    {
        $totalHaberes = (float) $this->items()->where('type', 'haber')->sum('amount');
        $totalDeductions = (float) $this->items()->where('type', 'deduction')->sum('amount');

        $this->update([
            'total_haberes'    => $totalHaberes,
            'total_deductions' => $totalDeductions,
            'net_amount'       => $totalHaberes - $totalDeductions,
            'pdf_path'         => null,
        ]);
    }
}
