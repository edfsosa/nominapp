<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la columna first_installment_days a loans y el setting global correspondiente.
 *
 * first_installment_days: días desde la aprobación hasta el vencimiento de la primera cuota.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedSmallInteger('first_installment_days')->default(30)->after('installment_amount');
        });

        DB::table('settings')
            ->where('group', 'payroll')
            ->where('name', 'loan_first_installment_days')
            ->exists() || DB::table('settings')->insert([
                'group' => 'payroll',
                'name' => 'loan_first_installment_days',
                'payload' => '30',
                'locked' => false,
            ]);
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('first_installment_days');
        });

        DB::table('settings')
            ->where('group', 'payroll')
            ->where('name', 'loan_first_installment_days')
            ->delete();
    }
};
