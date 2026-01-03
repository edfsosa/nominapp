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
        Schema::table('attendance_days', function (Blueprint $table) {
            $table->boolean('is_extraordinary_work')->default(false)->after('is_weekend');
            $table->boolean('is_calculated')->default(false)->after('justified_absence');
            $table->timestamp('calculated_at')->nullable()->after('is_calculated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            $table->dropColumn('is_extraordinary_work');
            $table->dropColumn('is_calculated');
            $table->dropColumn('calculated_at');
        });
    }
};
