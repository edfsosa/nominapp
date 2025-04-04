<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deduction extends Model
{
    protected $fillable = ['payroll_id', 'name', 'amount', 'type'];

    public function payroll() : BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }
}
