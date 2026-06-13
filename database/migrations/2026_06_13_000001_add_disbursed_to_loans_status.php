<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Agrega el estado 'disbursed' al enum de status en la tabla loans.
 *
 * Ciclo de vida actualizado:
 * pending → approved → disbursed → paid
 * pending → rejected
 * pending/approved → cancelled
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending','approved','disbursed','paid','rejected','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending','approved','paid','rejected','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
