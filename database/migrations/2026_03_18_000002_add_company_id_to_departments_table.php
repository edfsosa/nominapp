<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('company_id')->after('id')->constrained()->cascadeOnDelete();
            $table->string('cost_center', 30)->nullable()->after('name');
            $table->string('description', 255)->nullable()->after('cost_center');

            $table->dropUnique(['name']);
            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'name']);
            $table->dropForeign(['company_id']);
            $table->dropColumn(['company_id', 'cost_center', 'description']);
            $table->unique(['name']);
        });
    }
};
