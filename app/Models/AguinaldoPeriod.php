<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

class AguinaldoPeriod extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    /** @var array<int, string> Campos auditados en el historial de cambios. */
    protected array $auditInclude = [
        'status',
        'closed_at',
    ];

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
            'draft' => 'Borrador',
            'processing' => 'En Proceso',
            'closed' => 'Cerrado',
        ];
    }

    public static function getStatusLabel(string $status): string
    {
        return self::getStatusOptions()[$status] ?? $status;
    }

    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'draft' => 'gray',
            'processing' => 'warning',
            'closed' => 'success',
            default => 'primary',
        };
    }

    public static function getStatusIcon(string $status): string
    {
        return match ($status) {
            'draft' => 'heroicon-o-pencil',
            'processing' => 'heroicon-o-cog-6-tooth',
            'closed' => 'heroicon-o-lock-closed',
            default => 'heroicon-o-question-mark-circle',
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
            'closed_at' => 'Fecha de cierre',
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
            'closed_at' => \Carbon\Carbon::parse($value)->format('d/m/Y H:i'),
            default => (string) $value,
        };
    }
}
