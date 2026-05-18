<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cambia disbursed_at de date a datetime para registrar hora exacta de entrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advances', function (Blueprint $table) {
            $table->dateTime('disbursed_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('advances', function (Blueprint $table) {
            $table->date('disbursed_at')->nullable()->change();
        });
    }
};
