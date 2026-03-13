<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Normalizar valores existentes antes de convertir a enum
        DB::table('payrolls')->whereNotIn('status', ['draft', 'approved', 'paid'])->update(['status' => 'draft']);

        Schema::table('payrolls', function (Blueprint $table) {
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->string('status')->default('draft')->change();
        });
    }
};
