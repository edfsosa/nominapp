<?php

namespace App\Console\Commands;

use App\Models\Contract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Marca como 'expired' los contratos activos cuya end_date ya pasó.
 * Se ejecuta diariamente para mantener los estados sincronizados con la fecha real.
 */
class ExpireContracts extends Command
{
    protected $signature = 'contracts:expire';

    protected $description = 'Marca como vencidos los contratos activos cuya fecha de fin ya pasó';

    public function handle(): int
    {
        $count = Contract::query()
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', now()->startOfDay())
            ->update(['status' => 'expired']);

        if ($count > 0) {
            Log::info("contracts:expire: {$count} contrato(s) marcados como vencidos.");
            $this->info("{$count} contrato(s) marcados como vencidos.");
        } else {
            $this->info('Sin contratos para vencer.');
        }

        return self::SUCCESS;
    }
}
