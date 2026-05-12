<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_bank_accounts', function (Blueprint $table) {
            $table->string('bank_company_id', 50)->nullable()->after('bank')
                ->comment('ID asignado por el banco a la empresa para pagos en lote');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_bank_accounts', function (Blueprint $table) {
            $table->dropColumn('bank_company_id');
        });
    }
};
