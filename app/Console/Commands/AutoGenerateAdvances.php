<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Loan;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Genera y activa adelantos de salario automáticamente para los empleados
 * cuyo contrato activo tiene `advance_percent` definido.
 *
 * Se ejecuta el 1° de cada mes. Por cada empleado elegible:
 *  - Verifica que no tenga adelanto pendiente/activo
 *  - Verifica que no tenga nómina del período actual ya generada
 *  - Crea el Loan y lo activa en una sola transacción
 *
 * Uso: php artisan advances:auto-generate
 */
class AutoGenerateAdvances extends Command
{
    /** @var string Nombre del comando Artisan. */
    protected $signature = 'advances:auto-generate
                            {--dry-run : Muestra qué se generaría sin persistir cambios}';

    /** @var string Descripción del comando. */
    protected $description = 'Genera y activa adelantos automáticos para empleados con advance_percent configurado en su contrato.';

    /**
     * Ejecuta el comando.
     *
     * @return int
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('[DRY RUN] No se persistirán cambios.');
        }

        $contracts = Contract::query()
            ->where('status', 'active')
            ->where('salary_type', 'mensual')
            ->whereNotNull('advance_percent')
            ->where('advance_percent', '>', 0)
            ->with('employee.activeContract')
            ->get();

        if ($contracts->isEmpty()) {
            $this->info('No hay empleados con adelanto automático configurado.');
            return self::SUCCESS;
        }

        $this->info("Procesando {$contracts->count()} contrato(s)...");

        $generated = 0;
        $skipped   = 0;
        $failed    = 0;

        foreach ($contracts as $contract) {
            $employee = $contract->employee;

            if (!$employee || $employee->status !== 'active') {
                $skipped++;
                continue;
            }

            // Verificar que no tenga adelanto pendiente o activo
            $existing = Loan::where('employee_id', $employee->id)
                ->where('type', 'advance')
                ->whereIn('status', ['pending', 'active'])
                ->exists();

            if ($existing) {
                $this->line("  Omitido: {$employee->full_name} — ya tiene adelanto activo/pendiente.");
                $skipped++;
                continue;
            }

            // Verificar que no tenga nómina del período actual generada
            $currentPeriod = PayrollPeriod::where('frequency', $contract->payroll_type)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->first();

            if ($currentPeriod) {
                $payrollExists = Payroll::where('employee_id', $employee->id)
                    ->where('payroll_period_id', $currentPeriod->id)
                    ->exists();

                if ($payrollExists) {
                    $this->line("  Omitido: {$employee->full_name} — la nómina del período ya fue generada.");
                    $skipped++;
                    continue;
                }
            }

            $amount = (int) round($contract->salary * $contract->advance_percent / 100);

            if ($amount <= 0) {
                $this->line("  Omitido: {$employee->full_name} — monto calculado es 0.");
                $skipped++;
                continue;
            }

            if ($isDryRun) {
                $this->line("  [DRY RUN] {$employee->full_name} — Gs. " . number_format($amount, 0, ',', '.') . " ({$contract->advance_percent}%)");
                $generated++;
                continue;
            }

            try {
                DB::transaction(function () use ($employee, $amount, $contract, &$generated, &$failed) {
                    $loan = Loan::create([
                        'employee_id'        => $employee->id,
                        'type'               => 'advance',
                        'amount'             => $amount,
                        'installments_count' => 1,
                        'installment_amount' => $amount,
                        'status'             => 'pending',
                        'reason'             => 'personal',
                    ]);

                    // Activar directamente (genera la cuota)
                    $result = $loan->activate(
                        grantedById: 1 // usuario sistema; ajustar si hay user ID de sistema definido
                    );

                    if (!$result['success']) {
                        throw new \RuntimeException($result['message']);
                    }

                    $generated++;
                    $this->line("  ✓ {$employee->full_name} — Gs. " . number_format($amount, 0, ',', '.') . " ({$contract->advance_percent}%)");
                });
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  ✗ {$employee->full_name} — {$e->getMessage()}");
                Log::warning('AutoGenerateAdvances: error al generar adelanto', [
                    'employee_id' => $employee->id,
                    'amount'      => $amount,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Resultado: {$generated} generados, {$skipped} omitidos, {$failed} con error.");

        Log::info('AutoGenerateAdvances completado', [
            'generated' => $generated,
            'skipped'   => $skipped,
            'failed'    => $failed,
            'dry_run'   => $isDryRun,
        ]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
