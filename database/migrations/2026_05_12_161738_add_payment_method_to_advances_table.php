<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega payment_method con default 'transfer' y luego asigna 'cash'
     * a los adelantos cuyos empleados tienen contrato activo con método de pago 'cash'.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('advances', 'payment_method')) {
            Schema::table('advances', function (Blueprint $table) {
                $table->string('payment_method')->default('transfer')->after('notes');
            });
        }

        // Actualizar adelantos existentes según método de pago del contrato activo
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("
                UPDATE advances a
                INNER JOIN employees e ON e.id = a.employee_id
                INNER JOIN contracts c ON c.employee_id = e.id AND c.status = 'active'
                SET a.payment_method = 'cash'
                WHERE c.payment_method = 'cash'
            ");
        } else {
            DB::statement("
                UPDATE advances SET payment_method = 'cash'
                WHERE employee_id IN (
                    SELECT e.id FROM employees e
                    INNER JOIN contracts c ON c.employee_id = e.id AND c.status = 'active'
                    WHERE c.payment_method = 'cash'
                )
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advances', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
