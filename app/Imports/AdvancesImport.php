<?php

namespace App\Imports;

use App\Models\Advance;
use App\Models\Employee;
use App\Settings\PayrollSettings;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

/** Procesa la importación masiva de adelantos desde el Excel plantilla. */
class AdvancesImport implements ToCollection, WithStartRow
{
    /** @var int Cantidad de adelantos creados exitosamente. */
    public int $created = 0;

    /**
     * Lista de filas fallidas con nombre del empleado y motivo.
     *
     * @var array<int, array{row: int, name: string, reason: string}>
     */
    public array $failures = [];

    /**
     * Comienza a leer desde la fila 2 (la 1 es el encabezado).
     */
    public function startRow(): int
    {
        return 2;
    }

    /**
     * Procesa cada fila del archivo y crea los adelantos válidos.
     *
     * Columnas esperadas:
     *  0 = CI, 1 = Nombre (referencia), 2 = Sucursal (referencia),
     *  3 = Monto (Gs.), 4 = Método de pago, 5 = Notas
     *
     * @param  Collection<int, Collection<int, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        $settings = app(PayrollSettings::class);
        $maxPerPeriod = $settings->advance_max_per_period;

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;
            $ci = trim((string) ($row[0] ?? ''));
            $amount = (float) str_replace(['.', ','], ['', '.'], (string) ($row[3] ?? ''));
            $paymentMethodRaw = trim((string) ($row[4] ?? ''));
            $notes = trim((string) ($row[5] ?? ''));

            if ($ci === '' && $amount === 0.0) {
                continue;
            }

            if ($ci === '') {
                $this->failures[] = ['row' => $rowNum, 'name' => '—', 'reason' => 'CI vacío'];

                continue;
            }

            $employee = Employee::where('ci', $ci)->where('status', 'active')->first();

            if (! $employee) {
                $this->failures[] = ['row' => $rowNum, 'name' => "CI {$ci}", 'reason' => 'Empleado no encontrado o inactivo'];

                continue;
            }

            if ($amount <= 0) {
                $this->failures[] = ['row' => $rowNum, 'name' => $employee->full_name, 'reason' => 'Monto inválido o vacío'];

                continue;
            }

            if (! $employee->getAdvanceReferenceSalary()) {
                $this->failures[] = ['row' => $rowNum, 'name' => $employee->full_name, 'reason' => 'Sin salario definido en contrato activo'];

                continue;
            }

            $max = $employee->getMaxAdvanceAmount();

            if ($max !== null && $amount > $max) {
                $percent = (int) $settings->advance_max_percent;
                $this->failures[] = [
                    'row' => $rowNum,
                    'name' => $employee->full_name,
                    'reason' => 'Monto supera el tope máximo ('.number_format($max, 0, ',', '.').' Gs., '.$percent.'% del salario)',
                ];

                continue;
            }

            if ($maxPerPeriod > 0) {
                $activeCount = Advance::where('employee_id', $employee->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->count();

                if ($activeCount >= $maxPerPeriod) {
                    $this->failures[] = [
                        'row' => $rowNum,
                        'name' => $employee->full_name,
                        'reason' => "Límite de {$maxPerPeriod} adelanto(s) activos alcanzado",
                    ];

                    continue;
                }
            }

            Advance::create([
                'employee_id' => $employee->id,
                'amount' => $amount,
                'status' => 'pending',
                'payment_method' => $this->resolvePaymentMethod($paymentMethodRaw),
                'notes' => $notes !== '' ? $notes : null,
            ]);

            $this->created++;
        }
    }

    /**
     * Convierte la etiqueta en español del método de pago al valor interno.
     */
    private function resolvePaymentMethod(string $raw): string
    {
        return match (mb_strtolower($raw)) {
            'efectivo', 'cash' => 'cash',
            default => 'transfer',
        };
    }
}
