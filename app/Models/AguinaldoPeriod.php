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
        'year'      => 'integer',
        'closed_at' => 'datetime',
    ];

    // =========================================================================
    // RELACIONES
    // =========================================================================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function aguinaldos(): HasMany
    {
        return $this->hasMany(Aguinaldo::class);
    }

    // =========================================================================
    // HELPERS ESTÁTICOS PARA ESTADOS
    // =========================================================================

    public static function getStatusOptions(): array
    {
        return [
            'draft'      => 'Borrador',
            'processing' => 'En Proceso',
            'closed'     => 'Cerrado',
        ];
    }

    public static function getStatusLabel(string $status): string
    {
        return self::getStatusOptions()[$status] ?? $status;
    }

    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'draft'      => 'gray',
            'processing' => 'warning',
            'closed'     => 'success',
            default      => 'primary',
        };
    }

    public static function getStatusIcon(string $status): string
    {
        return match ($status) {
            'draft'      => 'heroicon-o-pencil',
            'processing' => 'heroicon-o-cog-6-tooth',
            'closed'     => 'heroicon-o-lock-closed',
            default      => 'heroicon-o-question-mark-circle',
        };
    }

    // =========================================================================
    // VERIFICADORES DE ESTADO
    // =========================================================================

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    // =========================================================================
    // ATRIBUTOS COMPUTADOS
    // =========================================================================

    public function getNameAttribute(): string
    {
        return "Aguinaldo {$this->year} - {$this->company?->name}";
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabel($this->status);
    }

    public function getPendingAguinaldosCountAttribute(): int
    {
        return $this->aguinaldos()->where('status', 'pending')->count();
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeOfYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeOfCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
