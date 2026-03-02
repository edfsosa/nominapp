<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Generar contratos para empleados activos que aún no tienen ninguno.
        //    Si ya se ejecutó el comando manualmente antes, esto no hace nada.
        Artisan::call('contracts:generate-from-employees');

        // 2. Copiar payroll_type de employees a sus contratos activos existentes
        //    (cubre a los empleados que ya tenían contrato antes de esta migración).
        DB::table('contracts')
            ->join('employees', 'contracts.employee_id', '=', 'employees.id')
            ->where('contracts.status', 'active')
            ->whereNotNull('employees.payroll_type')
            ->update(['contracts.payroll_type' => DB::raw('employees.payroll_type')]);

        // 3. Eliminar columnas que ahora viven en contracts
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['position_id']);
            $table->dropColumn([
                'position_id',
                'base_salary',
                'daily_rate',
                'payroll_type',
                'employment_type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable()->constrained();
            $table->enum('payroll_type', ['monthly', 'biweekly', 'weekly'])->nullable();
            $table->enum('employment_type', ['full_time', 'day_laborer'])->nullable();
            $table->decimal('base_salary', 12, 2)->nullable();
            $table->decimal('daily_rate', 12, 2)->nullable();
        });
    }
};
