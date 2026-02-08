<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidacionItem extends Model
{
    protected $fillable = [
        'liquidacion_id',
        'type',
        'category',
        'description',
        'amount',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class);
    }

    public static function formatCurrency(float|int|null $amount): string
    {
        if ($amount === null) {
            return 'Gs. 0';
        }

        return 'Gs. ' . number_format($amount, 0, ',', '.');
    }

    public function getFormattedAmountAttribute(): string
    {
        return self::formatCurrency($this->amount);
    }
}
