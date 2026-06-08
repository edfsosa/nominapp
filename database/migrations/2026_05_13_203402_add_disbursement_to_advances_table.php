<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el estado 'disbursed' (dinero entregado al empleado, pendiente de descuento en nómina)
     * y la columna disbursement_batch_at (fecha_credito del lote bancario).
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE advances MODIFY COLUMN status ENUM('pending','approved','disbursed','paid','rejected','cancelled') NOT NULL DEFAULT 'pending'");
        }

        if (! Schema::hasColumn('advances', 'disbursement_batch_at')) {
            Schema::table('advances', function (Blueprint $table) {
                $table->date('disbursement_batch_at')->nullable()->after('payment_method');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advances', function (Blueprint $table) {
            $table->dropColumn('disbursement_batch_at');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE advances MODIFY COLUMN status ENUM('pending','approved','paid','rejected','cancelled') NOT NULL DEFAULT 'pending'");
        }
    }
};
