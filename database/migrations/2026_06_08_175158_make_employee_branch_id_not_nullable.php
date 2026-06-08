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

        $this->dropForeignKeyOnColumn('employees', 'branch_id');

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable(false)->change();
            $table->foreign('branch_id')->references('id')->on('branches');
        });
    }

    public function down(): void
    {
        $this->dropForeignKeyOnColumn('employees', 'branch_id');

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->change();
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });
    }

    /**
     * Drops all foreign keys on a given column, regardless of their generated name.
     */
    private function dropForeignKeyOnColumn(string $tableName, string $columnName): void
    {
        $fks = DB::select(
            "SELECT kcu.CONSTRAINT_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
             JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
               ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
              AND tc.TABLE_SCHEMA = kcu.TABLE_SCHEMA
              AND tc.TABLE_NAME = kcu.TABLE_NAME
             WHERE kcu.TABLE_SCHEMA = DATABASE()
               AND kcu.TABLE_NAME = ?
               AND kcu.COLUMN_NAME = ?
               AND tc.CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$tableName, $columnName]
        );

        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }
    }
};
