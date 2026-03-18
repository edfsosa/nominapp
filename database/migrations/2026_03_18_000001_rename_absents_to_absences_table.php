<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('absents', 'absences');
    }

    public function down(): void
    {
        Schema::rename('absences', 'absents');
    }
};
