<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    protected $fillable = [
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
        'end_date'   => 'date',
        'closed_at'  => 'datetime',
    ];

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public static function frequencyOptions(): array
    {
        return [
            'monthly'  => 'Mensual',
            'biweekly' => 'Quincenal',
            'weekly'   => 'Semanal',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'draft'      => 'Borrador',
            'processing' => 'En Proceso',
            'closed'     => 'Cerrado',
        ];
    }

    public static function statusColors(): array
    {
        return [
            'draft'      => 'gray',
            'processing' => 'warning',
            'closed'     => 'success',
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
            'monthly'  => ucfirst($start->locale('es')->isoFormat('MMMM YYYY')),
            'biweekly' => 'Quincena ' . $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y'),
            'weekly'   => 'Semana del ' . $start->format('d/m/Y') . ' al ' . $end->format('d/m/Y'),
            default    => $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y'),
        };
    }
}
