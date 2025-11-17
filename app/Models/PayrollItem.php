<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_id',
        'type',
        'description',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Relación con el modelo Payroll, un item pertenece a una nómina
     */
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    // Scopes
    public function scopePerceptions($query)
    {
        return $query->where('type', 'perception');
    }

    public function scopeDeductions($query)
    {
        return $query->where('type', 'deduction');
    }
}
