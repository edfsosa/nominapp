<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Employee;
use Illuminate\Console\Command;

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

        $employees = Employee::where('status', 'active')
            ->whereDoesntHave('contracts')
            ->with('position.department')
            ->get();

        if ($employees->isEmpty()) {
            $this->info('Todos los empleados activos ya tienen al menos un contrato. Nada que hacer.');
            return self::SUCCESS;
        }

        $this->info("Empleados activos sin contrato encontrados: {$employees->count()}");
        $this->newLine();

        $created = 0;
        $skipped = [];

        foreach ($employees as $employee) {
            if (! $employee->position_id) {
                $skipped[] = [$employee->full_name, 'Sin cargo asignado (position_id nulo)'];
                continue;
            }

            $departmentId = $employee->position?->department_id;

            if (! $departmentId) {
                $skipped[] = [$employee->full_name, "Cargo sin departamento (position_id: {$employee->position_id})"];
                continue;
            }

            $isDayLaborer = $employee->employment_type === 'day_laborer';
            $salary = $isDayLaborer ? $employee->daily_rate : $employee->base_salary;

            if (! $salary || $salary <= 0) {
                $skipped[] = [$employee->full_name, 'Sin salario definido (base_salary y daily_rate son nulos)'];
                continue;
            }

            $salaryType   = $isDayLaborer ? 'jornal' : 'mensual';
            $payrollType  = $employee->payroll_type ?? 'monthly';
            $salaryLabel  = number_format((int) $salary, 0, ',', '.');

            $this->line("  → {$employee->full_name}: {$salaryType}, Gs. {$salaryLabel}/".($isDayLaborer ? 'día' : 'mes').", nómina: {$payrollType}");

            if (! $dryRun) {
                Contract::create([
                    'employee_id'   => $employee->id,
                    'type'          => 'indefinido',
                    'start_date'    => $employee->hire_date,
                    'salary_type'   => $salaryType,
                    'salary'        => (int) $salary,
                    'payroll_type'  => $payrollType,
                    'position_id'   => $employee->position_id,
                    'department_id' => $departmentId,
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
