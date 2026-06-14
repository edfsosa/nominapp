<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aguinaldos', function (Blueprint $table) {
            if (! Schema::hasColumn('aguinaldos', 'disbursement_batch_id')) {
                $table->foreignId('disbursement_batch_id')
                    ->nullable()
                    ->after('status')
                    ->constrained('disbursement_batches')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('aguinaldos', 'payment_method')) {
                $table->string('payment_method')->default('cash')->after('disbursement_batch_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('aguinaldos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disbursement_batch_id');
            $table->dropColumn('payment_method');
        });
    }
};
