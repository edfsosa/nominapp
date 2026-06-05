<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\VacationService;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Genera los balances anuales de vacaciones para todos los empleados activos.
 * Se ejecuta automáticamente el 1° de enero vía scheduler.
 * También puede ejecutarse manualmente para cualquier año.
 */
class GenerateVacationBalances extends Command
{
    protected $signature = 'vacations:generate-annual-balances
                            {--year= : Año para el que se generan los balances (default: año actual)}';

    protected $description = 'Genera balances de vacaciones para todos los empleados activos del año indicado';

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?? now()->year);

        $this->info("Generando balances de vacaciones para el año {$year}...");

        $result = VacationService::generateBalancesForYear($year);

        Log::info("Balances de vacaciones generados para {$year}", $result);

        $this->info("✓ Creados: {$result['created']} | Omitidos (ya existían): {$result['skipped']}");

        if ($result['created'] > 0) {
            $body = "Se generaron {$result['created']} balance(s) de vacaciones para {$year}.";
            if ($result['skipped'] > 0) {
                $body .= " Se omitieron {$result['skipped']} que ya existían.";
            }

            Notification::make()
                ->success()
                ->title("Balances de vacaciones {$year} generados")
                ->body($body)
                ->sendToDatabase(User::all());
        }

        return Command::SUCCESS;
    }
}
