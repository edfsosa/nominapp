<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campos de pago bancario a payrolls:
 * - payment_method: método de pago (transfer/cash), auto-relleno desde el contrato del empleado
 * - disbursed_at / disbursed_by_id: cuándo y quién acreditó el pago
 * - disbursement_batch_id: lote bancario al que pertenece este recibo (opcional)
 * - bank_rejection_reason: motivo de rechazo bancario (si aplica)
 * - status: se amplía con el estado 'disbursed' (acreditado al banco, pendiente de confirmación)
 *
 * Nuevo ciclo de vida: draft → approved → disbursed → paid
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Ampliar el enum de status para incluir 'disbursed'
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payrolls MODIFY COLUMN status ENUM('draft','approved','disbursed','paid') NOT NULL DEFAULT 'draft'");
        }

        Schema::table('payrolls', function (Blueprint $table) {
            if (! Schema::hasColumn('payrolls', 'payment_method')) {
                $table->enum('payment_method', ['transfer', 'cash'])->nullable()->after('status');
            }
            if (! Schema::hasColumn('payrolls', 'disbursed_at')) {
                $table->timestamp('disbursed_at')->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('payrolls', 'disbursed_by_id')) {
                $table->foreignId('disbursed_by_id')->nullable()->after('disbursed_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('payrolls', 'disbursement_batch_id')) {
                $table->foreignId('disbursement_batch_id')->nullable()->after('disbursed_by_id')
                    ->constrained('disbursement_batches')->nullOnDelete();
            }
            if (! Schema::hasColumn('payrolls', 'bank_rejection_reason')) {
                $table->string('bank_rejection_reason')->nullable()->after('disbursement_batch_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropForeign(['disbursed_by_id']);
            $table->dropForeign(['disbursement_batch_id']);
            $table->dropColumn([
                'payment_method',
                'disbursed_at',
                'disbursed_by_id',
                'disbursement_batch_id',
                'bank_rejection_reason',
            ]);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payrolls MODIFY COLUMN status ENUM('draft','approved','paid') NOT NULL DEFAULT 'draft'");
        }
    }
};
