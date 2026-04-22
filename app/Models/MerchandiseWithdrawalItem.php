<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ítem (producto) de un retiro de mercadería. */
class MerchandiseWithdrawalItem extends Model
{
    protected $fillable = [
        'merchandise_withdrawal_id',
        'code',
        'name',
        'description',
        'price',
        'quantity',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /** Retiro al que pertenece este ítem. */
    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(MerchandiseWithdrawal::class, 'merchandise_withdrawal_id');
    }
}
