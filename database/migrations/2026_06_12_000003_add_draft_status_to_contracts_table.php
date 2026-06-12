<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Agrega el valor 'draft' al enum status de la tabla contracts. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE contracts MODIFY COLUMN status ENUM('active','expired','terminated','renewed','suspended','draft') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("UPDATE contracts SET status = 'active' WHERE status = 'draft'");
        DB::statement("ALTER TABLE contracts MODIFY COLUMN status ENUM('active','expired','terminated','renewed','suspended') NOT NULL DEFAULT 'active'");
    }
};
