<?php

namespace App\Console\Commands;

use App\Models\Contract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateContractsFromEmployees extends Command
{
    protected $signature = 'contracts:generate-from-employees
                            {--dry-run : Muestra qué se crearía sin ejecutar cambios}';

    protected $description = 'Genera contratos iniciales para empleados activos que aún no tienen ningún contrato';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY RUN] No se realizarán cambios en la base de datos.');
            $this->newLine();
        }

        // Leer directamente de la tabla para no depender de los accessors del modelo,
        // ya que estos empleados aún no tienen contrato activo.
        $employees = DB::table('employees')
            ->where('employees.status', 'active')
            ->whereNotExists(fn ($q) =>
                $q->from('contracts')->whereColumn('contracts.employee_id', 'employees.id')
            )
            ->leftJoin('positions', 'employees.position_id', '=', 'positions.id')
            ->select(
                'employees.id',
                'employees.first_name',
                'employees.last_name',
                'employees.hire_date',
                'employees.employment_type',
                'employees.base_salary',
                'employees.daily_rate',
                'employees.payroll_type',
                'employees.position_id',
                'positions.department_id',
            )
            ->get();

        if ($employees->isEmpty()) {
            $this->info('Todos los empleados activos ya tienen al menos un contrato. Nada que hacer.');
            return self::SUCCESS;
        }

        $fullName = fn ($e) => trim($e->first_name . ' ' . $e->last_name);

        $this->info("Empleados activos sin contrato encontrados: {$employees->count()}");
        $this->newLine();

        $created = 0;
        $skipped = [];

        foreach ($employees as $employee) {
            if (! $employee->position_id) {
                $skipped[] = [$fullName($employee), 'Sin cargo asignado (position_id nulo)'];
                continue;
            }

            if (! $employee->department_id) {
                $skipped[] = [$fullName($employee), "Cargo sin departamento (position_id: {$employee->position_id})"];
                continue;
            }

            $isDayLaborer = $employee->employment_type === 'day_laborer';
            $salary       = $isDayLaborer ? $employee->daily_rate : $employee->base_salary;

            if (! $salary || $salary <= 0) {
                $skipped[] = [$fullName($employee), 'Sin salario definido (base_salary y daily_rate son nulos)'];
                continue;
            }

            $salaryType  = $isDayLaborer ? 'jornal' : 'mensual';
            $payrollType = $employee->payroll_type ?? 'monthly';
            $salaryLabel = number_format((int) $salary, 0, ',', '.');

            $this->line("  → {$fullName($employee)}: {$salaryType}, Gs. {$salaryLabel}/".($isDayLaborer ? 'día' : 'mes').", nómina: {$payrollType}");

            if (! $dryRun) {
                Contract::create([
                    'employee_id'   => $employee->id,
                    'type'          => 'indefinido',
                    'start_date'    => $employee->hire_date,
                    'salary_type'   => $salaryType,
                    'salary'        => (int) $salary,
                    'payroll_type'  => $payrollType,
                    'position_id'   => $employee->position_id,
                    'department_id' => $employee->department_id,
                    'work_modality' => 'presencial',
                    'status'        => 'active',
                ]);
            }

            $created++;
        }

        $this->newLine();
        $this->info('Contratos ' . ($dryRun ? 'a crear' : 'creados') . ": {$created}");

        if (! empty($skipped)) {
            $this->newLine();
            $this->warn('Empleados omitidos (' . count($skipped) . ') — requieren creación manual del contrato:');
            $this->table(['Empleado', 'Razón'], $skipped);
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('Para ejecutar los cambios, corra el comando sin --dry-run.');
        }

        return self::SUCCESS;
    }
}
