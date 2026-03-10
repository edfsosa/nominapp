<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar payment_method a contracts
        Schema::table('contracts', function (Blueprint $table) {
            $table->enum('payment_method', ['debit', 'cash', 'check'])
                ->default('debit')
                ->after('work_modality');
        });

        // 2. Copiar payment_method de cada empleado a todos sus contratos
        DB::statement('
            UPDATE contracts c
            INNER JOIN employees e ON e.id = c.employee_id
            SET c.payment_method = e.payment_method
        ');

        // 3. Eliminar hire_date y payment_method de employees
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['hire_date', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('hire_date')->nullable()->after('email');
            $table->enum('payment_method', ['debit', 'cash', 'check'])->default('debit')->after('hire_date');
        });

        DB::statement("
            UPDATE employees e
            INNER JOIN contracts c ON c.employee_id = e.id AND c.status = 'active'
            SET e.payment_method = c.payment_method
        ");

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
