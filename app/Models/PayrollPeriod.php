<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'start_date',
        'end_date',
        'frequency',
        'status',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    /** Resumen agrupado de percepciones para el infolist del período. */
    protected function perceptionSummary(): Attribute
    {
        return Attribute::make(
            get: fn () => PayrollItem::whereHas('payroll', fn ($q) => $q->where('payroll_period_id', $this->id))
                ->where('type', 'perception')
                ->selectRaw('description, count(*) as employees_count, sum(amount) as total_amount')
                ->groupBy('description')
                ->orderByDesc('employees_count')
                ->get()
                ->map(fn ($i) => [
                    'description' => $i->description,
                    'employees_count' => (int) $i->employees_count,
                    'total_amount' => (float) $i->total_amount,
                ])
                ->toArray()
        );
    }

    /** Resumen agrupado de deducciones para el infolist del período. */
    protected function deductionSummary(): Attribute
    {
        return Attribute::make(
            get: fn () => PayrollItem::whereHas('payroll', fn ($q) => $q->where('payroll_period_id', $this->id))
                ->where('type', 'deduction')
                ->selectRaw('description, count(*) as employees_count, sum(amount) as total_amount')
                ->groupBy('description')
                ->orderByDesc('employees_count')
                ->get()
                ->map(fn ($i) => [
                    'description' => $i->description,
                    'employees_count' => (int) $i->employees_count,
                    'total_amount' => (float) $i->total_amount,
                ])
                ->toArray()
        );
    }

    public static function frequencyOptions(): array
    {
        return [
            'monthly' => 'Mensual',
            'biweekly' => 'Quincenal',
            'weekly' => 'Semanal',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'draft' => 'Borrador',
            'processing' => 'En Proceso',
            'closed' => 'Cerrado',
        ];
    }

    public static function statusColors(): array
    {
        return [
            'draft' => 'gray',
            'processing' => 'warning',
            'closed' => 'success',
        ];
    }

    public function scopeOfFrequency($query, string $freq)
    {
        return $query->where('frequency', $freq);
    }

    // Genera el nombre del período según la frecuencia y el rango de fechas.
    // Centralizado aquí para garantizar consistencia en todos los puntos de creación.
    public static function generateName(string $frequency, Carbon $start, Carbon $end): string
    {
        return match ($frequency) {
            'monthly' => ucfirst($start->locale('es')->isoFormat('MMMM YYYY')),
            'biweekly' => 'Quincena '.$start->format('d/m/Y').' - '.$end->format('d/m/Y'),
            'weekly' => 'Semana del '.$start->format('d/m/Y').' al '.$end->format('d/m/Y'),
            default => $start->format('d/m/Y').' - '.$end->format('d/m/Y'),
        };
    }
}
