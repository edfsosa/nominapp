<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Hace branch_id NOT NULL en employees y cambia onDelete de SET NULL a RESTRICT. */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('employees')->whereNull('branch_id')->exists()) {
            throw new \RuntimeException('No se puede aplicar la migración: existen empleados sin sucursal asignada.');
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->unsignedBigInteger('branch_id')->nullable(false)->change();
            $table->foreign('branch_id')->references('id')->on('branches');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->unsignedBigInteger('branch_id')->nullable()->change();
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });
    }
};
