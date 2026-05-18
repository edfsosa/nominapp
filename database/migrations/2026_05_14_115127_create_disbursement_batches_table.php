<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla disbursement_batches para gestionar lotes de acreditación bancaria masiva.
 *
 * Un lote agrupa múltiples adelantos (y en el futuro: nóminas, vacaciones, aguinaldos)
 * para generar un único TXT/Excel de pagos bancarios (formato Itaú).
 *
 * Ciclo de vida:
 *   pending → confirmed (todos aceptados por el banco)
 *           → partially_confirmed (algunos rechazados por el banco)
 *           → cancelled (cancelado antes de enviar al banco)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursement_batches', function (Blueprint $table) {
            $table->id();

            $table->string('type', 30)->default('advances');
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            $table->date('fecha_credito');
            $table->enum('status', ['pending', 'confirmed', 'partially_confirmed', 'cancelled'])->default('pending');

            $table->string('file_path')->nullable();
            $table->string('bank_confirmation_path')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by_id')->constrained('users');
            $table->foreignId('confirmed_by_id')->nullable()->constrained('users');
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursement_batches');
    }
};
