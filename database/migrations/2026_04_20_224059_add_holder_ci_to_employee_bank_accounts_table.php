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
        Schema::table('employee_bank_accounts', function (Blueprint $table) {
            $table->string('holder_ci', 20)->nullable()->after('holder_name');
        });
    }

    public function down(): void
    {
        Schema::table('employee_bank_accounts', function (Blueprint $table) {
            $table->dropColumn('holder_ci');
        });
    }
};
