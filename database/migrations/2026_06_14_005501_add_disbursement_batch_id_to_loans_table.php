<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('loans', 'disbursement_batch_id')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->foreignId('disbursement_batch_id')
                    ->nullable()
                    ->after('payment_method')
                    ->constrained('disbursement_batches')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disbursement_batch_id');
        });
    }
};
