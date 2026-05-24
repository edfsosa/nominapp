<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el estado 'rejected' al ciclo de vida de retiros de mercadería
 * y registra quién y cuándo rechazó el retiro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchandise_withdrawals', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'paid', 'cancelled', 'rejected'])
                ->default('pending')
                ->change();

            $table->date('rejected_at')->nullable()->after('approved_by_id');
            $table->foreignId('rejected_by_id')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('merchandise_withdrawals', function (Blueprint $table) {
            $table->dropForeign(['rejected_by_id']);
            $table->dropColumn(['rejected_at', 'rejected_by_id']);

            $table->enum('status', ['pending', 'approved', 'paid', 'cancelled'])
                ->default('pending')
                ->change();
        });
    }
};
