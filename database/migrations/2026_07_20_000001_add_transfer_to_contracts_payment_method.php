<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega 'transfer' como método de pago válido en contracts.payment_method.
 * Necesario para el pago de sueldos vía transferencia bancaria (DisbursementBatch).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE contracts MODIFY payment_method ENUM('debit', 'cash', 'check', 'transfer') NOT NULL DEFAULT 'debit'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE contracts MODIFY payment_method ENUM('debit', 'cash', 'check') NOT NULL DEFAULT 'debit'");
        }
    }
};
