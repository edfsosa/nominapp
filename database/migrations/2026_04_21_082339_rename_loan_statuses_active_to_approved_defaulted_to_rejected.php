<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renombra los estados de préstamos para alinearlos con el módulo de Adelantos:
 *   active    → approved
 *   defaulted → rejected
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ampliar el ENUM antes de actualizar datos (permite valores viejos y nuevos durante la transición)
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending','active','approved','paid','rejected','cancelled','defaulted') NOT NULL DEFAULT 'pending'");

        DB::statement("UPDATE loans SET status = 'approved' WHERE status = 'active'");
        DB::statement("UPDATE loans SET status = 'rejected' WHERE status = 'defaulted'");

        // Dejar solo los valores nuevos
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending','approved','paid','rejected','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending','active','approved','paid','rejected','cancelled','defaulted') NOT NULL DEFAULT 'pending'");

        DB::statement("UPDATE loans SET status = 'active' WHERE status = 'approved'");
        DB::statement("UPDATE loans SET status = 'defaulted' WHERE status = 'rejected'");

        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending','active','paid','cancelled','defaulted') NOT NULL DEFAULT 'pending'");
    }
};
