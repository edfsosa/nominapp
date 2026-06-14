<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Amonestación laboral emitida a un empleado.
 *
 * Registro documental puro: no tiene ciclo de vida ni integración con nómina.
 */
class Warning extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    /** @var array<int, string> Campos auditados en el historial de cambios. */
    protected array $auditInclude = [
        'type',
        'reason',
        'description',
        'issued_at',
        'notes',
        'document_path',
    ];

    protected $fillable = [
        'employee_id',
        'type',
        'reason',
        'description',
        'issued_at',
        'issued_by_id',
        'notes',
        'document_path',
    ];

    protected $casts = [
        'issued_at' => 'date',
    ];

    // =========================================================================
    // RELACIONES
    // =========================================================================

    /** Empleado amonestado. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Usuario que emitió la amonestación. */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_id');
    }

    // =========================================================================
    // HELPERS ESTÁTICOS — TIPOS
    // =========================================================================

    /**
     * Retorna las opciones de tipo para selects.
     *
     * @return array<string, string>
     */
    public static function getTypeOptions(): array
    {
        return [
            'verbal' => 'Verbal',
            'written' => 'Escrita',
            'severe' => 'Grave',
        ];
    }

    /**
     * Retorna el label legible de un tipo.
     */
    public static function getTypeLabel(string $type): string
    {
        return self::getTypeOptions()[$type] ?? 'Desconocido';
    }

    /**
     * Retorna el color semántico Filament para un tipo.
     */
    public static function getTypeColor(string $type): string
    {
        return match ($type) {
            'verbal' => 'warning',
            'written' => 'danger',
            'severe' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Retorna el icono heroicon para un tipo.
     */
    public static function getTypeIcon(string $type): string
    {
        return match ($type) {
            'verbal' => 'heroicon-o-chat-bubble-left-ellipsis',
            'written' => 'heroicon-o-document-text',
            'severe' => 'heroicon-o-exclamation-triangle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    // =========================================================================
    // HELPERS ESTÁTICOS — MOTIVOS
    // =========================================================================

    /**
     * Retorna las opciones de motivo predefinidas para selects.
     *
     * @return array<string, string>
     */
    public static function getReasonOptions(): array
    {
        return [
            'tardanza' => 'Tardanza reiterada',
            'ausencia' => 'Ausencia injustificada',
            'incumplimiento' => 'Incumplimiento de normas',
            'conducta' => 'Conducta inapropiada',
            'negligencia' => 'Negligencia en el trabajo',
            'uso_indebido' => 'Uso indebido de recursos',
            'desobediencia' => 'Desobediencia a superiores',
            'conflicto' => 'Conflicto con compañeros',
            'rendimiento' => 'Bajo rendimiento',
            'otro' => 'Otro',
        ];
    }

    /**
     * Retorna el label legible de un motivo.
     */
    public static function getReasonLabel(string $reason): string
    {
        return self::getReasonOptions()[$reason] ?? $reason;
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
            'type' => 'Tipo',
            'reason' => 'Motivo',
            'description' => 'Descripción',
            'issued_at' => 'Fecha de emisión',
            'notes' => 'Notas',
            'document_path' => 'Documento firmado',
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
            'type' => static::getTypeLabel($value),
            'reason' => static::getReasonLabel($value),
            'issued_at' => \Carbon\Carbon::parse($value)->format('d/m/Y'),
            'document_path' => basename((string) $value),
            'description',
            'notes' => Str::limit((string) $value, 120),
            default => (string) $value,
        };
    }
}
