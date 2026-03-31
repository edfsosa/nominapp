<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return; // SQLite no soporta MODIFY COLUMN; enum se almacena como text sin restricción
        }

        DB::statement("ALTER TABLE attendance_days MODIFY COLUMN status ENUM('present', 'absent', 'on_leave', 'weekend', 'holiday') NOT NULL DEFAULT 'present'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE attendance_days MODIFY COLUMN status ENUM('present', 'absent', 'on_leave', 'holiday') NOT NULL DEFAULT 'present'");
    }
};
