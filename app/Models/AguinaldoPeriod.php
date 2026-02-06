<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AguinaldoPeriod extends Model
{
    protected $fillable = [
        'company_id',
        'year',
        'status',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'closed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function aguinaldos(): HasMany
    {
        return $this->hasMany(Aguinaldo::class);
    }

    public function getNameAttribute(): string
    {
        return "Aguinaldo {$this->year} - {$this->company?->name}";
    }

    public function scopeOfYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeOfCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
