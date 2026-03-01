<?php

namespace App\Console\Commands;

use App\Models\FaceEnrollment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpirePendingEnrollments extends Command
{
    protected $signature = 'face:expire-enrollments
                            {--dry-run : Ejecutar en modo prueba sin modificar registros}';
    protected $description = 'Marca como expirados los registros faciales en estado pending_capture cuyo expires_at ya pasó';

    /**
     * Ejecución del comando.
     *
     * @return int
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Buscando enrollments faciales expirados...');

        if ($dryRun) {
            $this->warn('Modo DRY-RUN activado - No se modificarán registros');
        }

        $query = FaceEnrollment::where('status', 'pending_capture')
            ->where('expires_at', '<', now());

        $count = $query->count();

        $this->info("Enrollments pendientes expirados encontrados: {$count}");

        if ($count === 0) {
            $this->info('No hay registros para procesar');
            return Command::SUCCESS;
        }

        if (!$dryRun) {
            $updated = FaceEnrollment::where('status', 'pending_capture')
                ->where('expires_at', '<', now())
                ->update(['status' => 'expired']);

            Log::info('Enrollments faciales expirados automáticamente', [
                'count'        => $updated,
                'executed_at'  => now()->toDateTimeString(),
            ]);

            $this->newLine();
            $this->table(
                ['Métrica', 'Cantidad'],
                [['Registros marcados como expirados', $updated]]
            );
        } else {
            $this->line("  [DRY-RUN] Se marcarían {$count} registro(s) como expirados");
        }

        return Command::SUCCESS;
    }
}
