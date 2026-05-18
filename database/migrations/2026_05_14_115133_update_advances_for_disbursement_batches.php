<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actualiza la tabla advances para el módulo DisbursementBatch:
 *
 *  - Renombra disbursement_batch_at → disbursed_at (fecha de entrega, todos los métodos)
 *  - Agrega disbursed_by_id (quién marcó como entregado)
 *  - Agrega transfer_receipt_path (comprobante para transferencias individuales)
 *  - Agrega disbursement_batch_id (FK al lote para transferencias masivas)
 *  - Agrega bank_rejection_reason (motivo de rechazo bancario, solo flujo de lotes)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advances', function (Blueprint $table) {
            $table->renameColumn('disbursement_batch_at', 'disbursed_at');
        });

        Schema::table('advances', function (Blueprint $table) {
            $table->foreignId('disbursed_by_id')->nullable()->after('disbursed_at')->constrained('users');
            $table->string('transfer_receipt_path')->nullable()->after('disbursed_by_id');
            $table->foreignId('disbursement_batch_id')->nullable()->after('transfer_receipt_path')->constrained('disbursement_batches')->nullOnDelete();
            $table->enum('bank_rejection_reason', [
                'cuenta_inexistente',
                'cuenta_bloqueada',
                'fondos_insuficientes',
                'datos_incorrectos',
                'otro',
            ])->nullable()->after('disbursement_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('advances', function (Blueprint $table) {
            $table->dropForeign(['disbursed_by_id']);
            $table->dropForeign(['disbursement_batch_id']);
            $table->dropColumn(['disbursed_by_id', 'transfer_receipt_path', 'disbursement_batch_id', 'bank_rejection_reason']);
        });

        Schema::table('advances', function (Blueprint $table) {
            $table->renameColumn('disbursed_at', 'disbursement_batch_at');
        });
    }
};
