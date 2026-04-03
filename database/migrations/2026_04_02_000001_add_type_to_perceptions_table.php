<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perceptions', function (Blueprint $table) {
            $table->string('type', 20)->default('salary')->after('code');
        });

        // Todos los registros existentes se clasifican como salariales
        DB::table('perceptions')->update(['type' => 'salary']);
    }

    public function down(): void
    {
        Schema::table('perceptions', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
