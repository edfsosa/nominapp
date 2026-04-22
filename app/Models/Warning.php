<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Amonestación laboral emitida a un empleado.
 *
 * Registro documental puro: no tiene ciclo de vida ni integración con nómina.
 */
class Warning extends Model
{
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
}
