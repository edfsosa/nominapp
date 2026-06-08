<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE contracts MODIFY COLUMN status ENUM('active','expired','terminated','renewed','suspended') NOT NULL DEFAULT 'active'");
        }
    }

    public function down(): void
    {
        DB::statement("UPDATE contracts SET status = 'active' WHERE status = 'suspended'");
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE contracts MODIFY COLUMN status ENUM('active','expired','terminated','renewed') NOT NULL DEFAULT 'active'");
        }
    }
};
