<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lote de acreditación bancaria masiva.
 *
 * Agrupa adelantos de salario (y en el futuro nóminas, vacaciones, aguinaldos)
 * para generar un único archivo TXT/Excel en formato Itaú.
 *
 * Ciclo de vida:
 *   pending → confirmed (todos aceptados por el banco)
 *           → partially_confirmed (algunos rechazados)
 *           → cancelled (cancelado antes de enviar al banco)
 */
class DisbursementBatch extends Model
{
    protected $fillable = [
        'type',
        'company_id',
        'fecha_credito',
        'status',
        'file_path',
        'bank_confirmation_path',
        'notes',
        'created_by_id',
        'confirmed_by_id',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_credito' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    // =========================================================================
    // RELACIONES
    // =========================================================================

    /** Empresa a la que pertenece el lote. */
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Adelantos incluidos en el lote. */
    public function advances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Advance::class);
    }

    /** Usuario que creó el lote. */
    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** Usuario que confirmó el resultado bancario. */
    public function confirmedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
    }

    // =========================================================================
    // VERIFICADORES DE ESTADO
    // =========================================================================

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isPartiallyConfirmed(): bool
    {
        return $this->status === 'partially_confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, ['confirmed', 'partially_confirmed', 'cancelled']);
    }

    // =========================================================================
    // HELPERS ESTÁTICOS — ESTADOS
    // =========================================================================

    /**
     * @return array<string, string>
     */
    public static function getStatusOptions(): array
    {
        return [
            'pending' => 'Pendiente',
            'confirmed' => 'Confirmado',
            'partially_confirmed' => 'Parcialmente confirmado',
            'cancelled' => 'Cancelado',
        ];
    }

    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'confirmed' => 'Confirmado',
            'partially_confirmed' => 'Parcial',
            'cancelled' => 'Cancelado',
            default => 'Desconocido',
        };
    }

    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'confirmed' => 'success',
            'partially_confirmed' => 'info',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    public static function getStatusIcon(string $status): string
    {
        return match ($status) {
            'pending' => 'heroicon-o-clock',
            'confirmed' => 'heroicon-o-check-circle',
            'partially_confirmed' => 'heroicon-o-exclamation-circle',
            'cancelled' => 'heroicon-o-minus-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    // =========================================================================
    // HELPERS ESTÁTICOS — TIPO
    // =========================================================================

    /**
     * @return array<string, string>
     */
    public static function getTypeOptions(): array
    {
        return [
            'advances' => 'Adelantos de salario',
        ];
    }

    public static function getTypeLabel(string $type): string
    {
        return match ($type) {
            'advances' => 'Adelantos',
            default => $type,
        };
    }

    // =========================================================================
    // ACCIONES PRINCIPALES
    // =========================================================================

    /**
     * Cancela el lote. Solo posible si está en estado pending.
     *
     * Revierte todos los adelantos del lote a 'approved', limpiando
     * disbursement_batch_id para que puedan incluirse en otro lote.
     *
     * @return array{success: bool, message: string}
     */
    public function cancel(): array
    {
        if (! $this->isPending()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden cancelar lotes en estado Pendiente.',
            ];
        }

        $this->advances()->update([
            'disbursement_batch_id' => null,
        ]);

        $this->update(['status' => 'cancelled']);

        return [
            'success' => true,
            'message' => 'El lote fue cancelado. Los adelantos volvieron al estado Aprobado.',
        ];
    }

    /**
     * Confirma el resultado bancario del lote.
     *
     * Marca los adelantos aceptados como 'disbursed' con disbursed_at = fecha_credito.
     * Los adelantos rechazados vuelven a 'approved' con bank_rejection_reason.
     *
     * @param  string  $bankConfirmationPath  Ruta del comprobante bancario (obligatorio).
     * @param  array<int>  $rejectedAdvanceIds  IDs de los adelantos rechazados por el banco.
     * @param  array<int, string>  $rejectionReasons  Mapa id => bank_rejection_reason para los rechazados.
     * @return array{success: bool, message: string}
     */
    public function confirm(
        int $confirmedById,
        string $bankConfirmationPath,
        array $rejectedAdvanceIds = [],
        array $rejectionReasons = [],
    ): array {
        if (! $this->isPending()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden confirmar lotes en estado Pendiente.',
            ];
        }

        $advances = $this->advances()->where('status', 'approved')->get();

        foreach ($advances as $advance) {
            if (in_array($advance->id, $rejectedAdvanceIds)) {
                $advance->update([
                    'disbursement_batch_id' => null,
                    'bank_rejection_reason' => $rejectionReasons[$advance->id] ?? 'otro',
                ]);
            } else {
                $advance->update([
                    'status' => 'disbursed',
                    'disbursed_at' => now(),
                    'disbursed_by_id' => $confirmedById,
                    'bank_rejection_reason' => null,
                ]);
            }
        }

        $allRejected = count($rejectedAdvanceIds) === $advances->count();
        $someRejected = count($rejectedAdvanceIds) > 0;

        $status = $allRejected ? 'cancelled' : ($someRejected ? 'partially_confirmed' : 'confirmed');

        $this->update([
            'status' => $status,
            'bank_confirmation_path' => $bankConfirmationPath,
            'confirmed_by_id' => $confirmedById,
            'confirmed_at' => now(),
        ]);

        $disbursedCount = $advances->count() - count($rejectedAdvanceIds);

        return [
            'success' => true,
            'message' => "Se acreditaron {$disbursedCount} adelantos. ".count($rejectedAdvanceIds).' rechazados por el banco.',
        ];
    }
}
