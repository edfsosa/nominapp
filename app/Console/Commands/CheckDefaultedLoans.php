<?php

namespace App\Console\Commands;

use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Detecta préstamos/adelantos activos con cuotas vencidas hace más de 30 días
 * y los marca automáticamente como en mora (defaulted).
 *
 * Se ejecuta diariamente vía scheduler. El umbral de 30 días cubre un ciclo
 * de nómina completo sin cobro, evitando falsos positivos por desfase de fechas.
 */
class CheckDefaultedLoans extends Command
{
    protected $signature   = 'loans:check-defaulted';
    protected $description = 'Marca como en mora los préstamos activos con cuotas vencidas hace más de 30 días';

    /** Umbral en días desde el vencimiento para considerar un préstamo en mora. */
    private const OVERDUE_THRESHOLD_DAYS = 30;

    /**
     * Ejecuta el comando.
     */
    public function handle(): int
    {
        $cutoffDate = Carbon::today()->subDays(self::OVERDUE_THRESHOLD_DAYS)->toDateString();

        // Préstamos activos que tienen al menos una cuota pendiente vencida antes del umbral
        $loans = Loan::where('status', 'active')
            ->whereHas('installments', fn($q) => $q
                ->where('status', 'pending')
                ->where('due_date', '<', $cutoffDate))
            ->get();

        if ($loans->isEmpty()) {
            $this->info('No se encontraron préstamos en mora.');
            return self::SUCCESS;
        }

        $marked = 0;

        foreach ($loans as $loan) {
            $result = $loan->markAsDefaulted('Marcado automáticamente por el sistema: cuota(s) vencida(s) hace más de ' . self::OVERDUE_THRESHOLD_DAYS . ' días.');

            if ($result['success']) {
                $marked++;
                Log::info('Préstamo marcado en mora automáticamente', [
                    'loan_id'     => $loan->id,
                    'employee_id' => $loan->employee_id,
                    'type'        => $loan->type,
                ]);
            }
        }

        $this->info("Se marcaron {$marked} préstamo(s)/adelanto(s) como en mora.");

        return self::SUCCESS;
    }
}
