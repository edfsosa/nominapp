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
        Schema::table('perceptions', function (Blueprint $table) {
            $table->string('code', 10)->unique()->after('name');
            $table->boolean('affects_ips')->default(false)->after('is_taxable');
            $table->boolean('affects_irp')->default(false)->after('affects_ips');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('perceptions', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'affects_ips', 'affects_irp']);
        });
    }
};
