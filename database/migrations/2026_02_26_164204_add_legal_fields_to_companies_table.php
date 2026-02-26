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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('legal_type')->nullable()->after('trade_name');
            $table->date('founded_at')->nullable()->after('legal_type');
            $table->string('legal_rep_name')->nullable()->after('founded_at');
            $table->string('legal_rep_ci', 20)->nullable()->after('legal_rep_name');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['legal_type', 'founded_at', 'legal_rep_name', 'legal_rep_ci']);
        });
    }
};
