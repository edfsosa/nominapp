<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lote de acreditación bancaria masiva.
 *
 * Agrupa adelantos (`advances`), recibos de nómina (`payroll`), préstamos (`loan`)
 * o aguinaldos (`aguinaldo`) para generar un único archivo TXT en formato Itaú y
 * registrar el resultado del banco.
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

    /** Recibos de nómina incluidos en el lote. */
    public function payrolls(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    /** Préstamos incluidos en el lote. */
    public function loans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /** Aguinaldos incluidos en el lote. */
    public function aguinaldos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Aguinaldo::class);
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
            'payroll' => 'Planilla de salarios',
            'loan' => 'Préstamos',
            'aguinaldo' => 'Aguinaldo',
        ];
    }

    public static function getTypeLabel(string $type): string
    {
        return match ($type) {
            'advances' => 'Adelantos',
            'payroll' => 'Planilla',
            'loan' => 'Préstamos',
            'aguinaldo' => 'Aguinaldo',
            default => $type,
        };
    }

    public static function getTypeColor(string $type): string
    {
        return match ($type) {
            'advances' => 'success',
            'payroll' => 'info',
            'loan' => 'warning',
            'aguinaldo' => 'primary',
            default => 'gray',
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

        match ($this->type) {
            'payroll' => $this->payrolls()->update(['disbursement_batch_id' => null]),
            'loan' => $this->loans()->update(['disbursement_batch_id' => null]),
            'aguinaldo' => $this->aguinaldos()->update(['disbursement_batch_id' => null]),
            default => $this->advances()->update(['disbursement_batch_id' => null]),
        };

        $this->update(['status' => 'cancelled']);

        $entity = match ($this->type) {
            'payroll' => 'Los recibos volvieron a estar disponibles.',
            'loan' => 'Los préstamos volvieron al estado Aprobado.',
            'aguinaldo' => 'Los aguinaldos volvieron al estado Pendiente.',
            default => 'Los adelantos volvieron al estado Aprobado.',
        };

        return [
            'success' => true,
            'message' => "El lote fue cancelado. {$entity}",
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
    /**
     * Confirma el resultado bancario del lote.
     *
     * Para lotes de adelantos: marca los aceptados como 'disbursed'.
     * Para lotes de nómina: marca los aceptados como 'disbursed'.
     * Los rechazados (si los hay) vuelven a 'approved' con bank_rejection_reason.
     *
     * @param  array<int>  $rejectedIds  IDs de registros rechazados por el banco.
     * @param  array<int, string>  $rejectionReasons  Mapa id => bank_rejection_reason.
     * @return array{success: bool, message: string}
     */
    public function confirm(
        int $confirmedById,
        string $bankConfirmationPath,
        array $rejectedIds = [],
        array $rejectionReasons = [],
    ): array {
        if (! $this->isPending()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden confirmar lotes en estado Pendiente.',
            ];
        }

        return match ($this->type) {
            'payroll' => $this->confirmPayrolls($confirmedById, $bankConfirmationPath, $rejectedIds, $rejectionReasons),
            'loan' => $this->confirmLoans($confirmedById, $bankConfirmationPath, $rejectedIds, $rejectionReasons),
            'aguinaldo' => $this->confirmAguinaldos($confirmedById, $bankConfirmationPath, $rejectedIds),
            default => $this->confirmAdvances($confirmedById, $bankConfirmationPath, $rejectedIds, $rejectionReasons),
        };
    }

    /** @param array<int> $rejectedIds @param array<int,string> $rejectionReasons */
    private function confirmAdvances(int $confirmedById, string $bankConfirmationPath, array $rejectedIds, array $rejectionReasons): array
    {
        $advances = $this->advances()->where('status', 'approved')->get();

        foreach ($advances as $advance) {
            if (in_array($advance->id, $rejectedIds)) {
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

        $allRejected = count($rejectedIds) === $advances->count();
        $someRejected = count($rejectedIds) > 0;
        $status = $allRejected ? 'cancelled' : ($someRejected ? 'partially_confirmed' : 'confirmed');
        $disbursedCount = $advances->count() - count($rejectedIds);

        $this->update([
            'status' => $status,
            'bank_confirmation_path' => $bankConfirmationPath,
            'confirmed_by_id' => $confirmedById,
            'confirmed_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => "Se acreditaron {$disbursedCount} adelantos. ".count($rejectedIds).' rechazados por el banco.',
        ];
    }

    /** @param array<int> $rejectedIds @param array<int,string> $rejectionReasons */
    private function confirmPayrolls(int $confirmedById, string $bankConfirmationPath, array $rejectedIds, array $rejectionReasons): array
    {
        $payrolls = $this->payrolls()->where('status', 'approved')->get();

        foreach ($payrolls as $payroll) {
            if (in_array($payroll->id, $rejectedIds)) {
                $payroll->update([
                    'disbursement_batch_id' => null,
                    'bank_rejection_reason' => $rejectionReasons[$payroll->id] ?? 'otro',
                ]);
            } else {
                $payroll->update([
                    'status' => 'disbursed',
                    'disbursed_at' => now(),
                    'disbursed_by_id' => $confirmedById,
                    'bank_rejection_reason' => null,
                ]);
            }
        }

        $allRejected = count($rejectedIds) === $payrolls->count();
        $someRejected = count($rejectedIds) > 0;
        $status = $allRejected ? 'cancelled' : ($someRejected ? 'partially_confirmed' : 'confirmed');
        $disbursedCount = $payrolls->count() - count($rejectedIds);

        $this->update([
            'status' => $status,
            'bank_confirmation_path' => $bankConfirmationPath,
            'confirmed_by_id' => $confirmedById,
            'confirmed_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => "Se acreditaron {$disbursedCount} recibos de nómina. ".count($rejectedIds).' rechazados por el banco.',
        ];
    }

    /** @param array<int> $rejectedIds @param array<int,string> $rejectionReasons */
    private function confirmLoans(int $confirmedById, string $bankConfirmationPath, array $rejectedIds, array $rejectionReasons): array
    {
        $loans = $this->loans()->where('status', 'approved')->get();

        foreach ($loans as $loan) {
            if (in_array($loan->id, $rejectedIds)) {
                $loan->update([
                    'disbursement_batch_id' => null,
                    'status' => 'approved',
                ]);
            } else {
                $loan->disburse($confirmedById);
            }
        }

        $allRejected = count($rejectedIds) === $loans->count();
        $someRejected = count($rejectedIds) > 0;
        $status = $allRejected ? 'cancelled' : ($someRejected ? 'partially_confirmed' : 'confirmed');
        $disbursedCount = $loans->count() - count($rejectedIds);

        $this->update([
            'status' => $status,
            'bank_confirmation_path' => $bankConfirmationPath,
            'confirmed_by_id' => $confirmedById,
            'confirmed_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => "Se desembolsaron {$disbursedCount} préstamos. ".count($rejectedIds).' rechazados por el banco.',
        ];
    }

    /** @param array<int> $rejectedIds */
    private function confirmAguinaldos(int $confirmedById, string $bankConfirmationPath, array $rejectedIds): array
    {
        $aguinaldos = $this->aguinaldos()->where('status', 'pending')->get();

        foreach ($aguinaldos as $aguinaldo) {
            if (in_array($aguinaldo->id, $rejectedIds)) {
                $aguinaldo->update(['disbursement_batch_id' => null]);
            } else {
                $aguinaldo->markAsPaid();
            }
        }

        $allRejected = count($rejectedIds) === $aguinaldos->count();
        $someRejected = count($rejectedIds) > 0;
        $status = $allRejected ? 'cancelled' : ($someRejected ? 'partially_confirmed' : 'confirmed');
        $disbursedCount = $aguinaldos->count() - count($rejectedIds);

        $this->update([
            'status' => $status,
            'bank_confirmation_path' => $bankConfirmationPath,
            'confirmed_by_id' => $confirmedById,
            'confirmed_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => "Se acreditaron {$disbursedCount} aguinaldos. ".count($rejectedIds).' rechazados por el banco.',
        ];
    }
}
