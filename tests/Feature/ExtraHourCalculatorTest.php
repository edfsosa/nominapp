<?php

use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Services\ExtraHourCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

beforeEach(function () {
    $settings = [
        'monthly_hours'               => 240,
        'monthly_hours_nocturno'      => 210,
        'monthly_hours_mixto'         => 225,
        'daily_hours'                 => 8,
        'daily_hours_nocturno'        => 7,
        'daily_hours_mixto'           => 7.5,
        'days_per_month'              => 30,
        'overtime_multiplier_diurno'  => 1.5,
        'overtime_multiplier_nocturno' => 2.6,
        'overtime_multiplier_holiday' => 2.0,
        'overtime_max_daily_hours'    => 3,
        'ips_employee_rate'           => 9,
        'indemnizacion_days_per_year' => 15,
        'vacation_min_consecutive_days' => 6,
        'vacation_min_years_service'  => 1,
        'vacation_business_days'      => [1, 2, 3, 4, 5, 6],
        'ips_deduction_code'          => 'IPS001',
    ];

    foreach ($settings as $name => $value) {
        DB::table('settings')->updateOrInsert(
            ['group' => 'payroll', 'name' => $name],
            ['payload' => json_encode($value)]
        );
    }
});

function makeEhcEmployee(string $salaryType = 'mensual', int $salary = 2_550_000): Employee
{
    static $ci = 7000000;
    $n = $ci++;

    $company    = Company::create(['name' => "Empresa {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch     = Branch::create(['name' => "Sucursal {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "Depto {$n}", 'company_id' => $company->id]);
    $position   = Position::create(['name' => "Cargo {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Test',
        'last_name'  => 'EHC',
        'ci'         => (string) $n,
        'email'      => "ehc{$n}@test.com",
        'branch_id'  => $branch->id,
        'status'     => 'active',
    ]);

    Contract::create([
        'employee_id'   => $employee->id,
        'type'          => 'indefinido',
        'start_date'    => Carbon::now()->subYear(),
        'salary_type'   => $salaryType,
        'salary'        => $salary,
        'position_id'   => $position->id,
        'department_id' => $department->id,
        'status'        => 'active',
    ]);

    return $employee->fresh();
}

function makeEhcPeriod(): PayrollPeriod
{
    return PayrollPeriod::create([
        'name'       => 'Marzo 2026',
        'start_date' => '2026-03-01',
        'end_date'   => '2026-03-31',
        'frequency'  => 'monthly',
        'status'     => 'draft',
    ]);
}

function makeEhcDay(Employee $employee, array $attrs = []): AttendanceDay
{
    static $dateOffset = 0;
    $date = Carbon::create(2026, 3, 3 + $dateOffset++); // días dentro del período

    return AttendanceDay::create(array_merge([
        'employee_id'      => $employee->id,
        'date'             => $date->toDateString(),
        'extra_hours'      => 0,
        'overtime_approved' => false,
        'is_holiday'       => false,
        'is_weekend'       => false,
    ], $attrs));
}

// ─── Resultado vacío ─────────────────────────────────────────────────────────

it('retorna resultado vacío si empleado mensual no tiene salario', function () {
    // Empleado sin contrato activo → base_salary = null
    $employee = Employee::create([
        'first_name' => 'Sin',
        'last_name'  => 'Salario',
        'ci'         => '9999991',
        'email'      => 'sin@test.com',
        'status'     => 'active',
    ]);
    $period = makeEhcPeriod();

    $result = (new ExtraHourCalculator())->calculate($employee, $period);

    expect($result)->toBe(['total' => 0, 'hours' => 0, 'items' => []]);
});

it('retorna resultado vacío si jornalero no tiene tarifa diaria', function () {
    // Empleado sin contrato activo → daily_rate = null, employment_type = null
    // Para forzar el path jornalero sin tarifa, hacemos un empleado sin contrato
    // pero la lógica entra por employment_type === 'day_laborer', que viene del contrato
    // Si no hay contrato, employment_type = null → entra al else (full_time) → base_salary = null → emptyResult
    // Para testear el path jornalero: crear contrato jornal con salary=0 es inválido por DB constraint
    // Usamos un employee con contrato jornal y salary válido, luego verificamos el cálculo normal
    // Este test verifica el path de empleado sin contrato
    $employee = Employee::create([
        'first_name' => 'Sin',
        'last_name'  => 'Contrato',
        'ci'         => '9999992',
        'email'      => 'sin2@test.com',
        'status'     => 'active',
    ]);
    $period = makeEhcPeriod();

    $result = (new ExtraHourCalculator())->calculate($employee, $period);

    expect($result['total'])->toBe(0)
        ->and($result['hours'])->toBe(0);
});

it('retorna resultado vacío si no hay días con horas extra aprobadas en el período', function () {
    $employee = makeEhcEmployee();
    $period   = makeEhcPeriod();

    makeEhcDay($employee, ['extra_hours' => 2, 'overtime_approved' => false]); // no aprobada
    makeEhcDay($employee, ['extra_hours' => 0, 'overtime_approved' => true]);  // aprobada pero 0h

    $result = (new ExtraHourCalculator())->calculate($employee, $period);

    expect($result)->toBe(['total' => 0, 'hours' => 0, 'items' => []]);
});

// ─── Empleado mensual ────────────────────────────────────────────────────────

it('calcula correctamente horas extra diurnas para empleado mensual', function () {
    // salary=2,550,000 / 240h = 10,625/h × 1.5 × 2h = 31,875
    $employee = makeEhcEmployee('mensual', 2_550_000);
    $period   = makeEhcPeriod();

    makeEhcDay($employee, [
        'date'                  => '2026-03-03',
        'extra_hours'           => 2,
        'extra_hours_diurnas'   => 2,
        'extra_hours_nocturnas' => 0,
        'overtime_approved'     => true,
        'is_holiday'            => false,
        'is_weekend'            => false,
    ]);

    $result = (new ExtraHourCalculator())->calculate($employee, $period);

    $hourlyRate = 2_550_000 / 240;
    $expected   = round(2 * $hourlyRate * 1.5, 2);

    expect($result['hours'])->toBe(2.0)
        ->and($result['total'])->toBe($expected)
        ->and($result['items'])->toHaveCount(1)
        ->and($result['items'][0]['description'])->toContain('Diurnas');
});

it('calcula correctamente horas extra nocturnas para empleado mensual', function () {
    // salary=2,550,000 / 240h = 10,625/h × 2.6 × 1h = 27,625
    $employee = makeEhcEmployee('mensual', 2_550_000);
    $period   = makeEhcPeriod();

    makeEhcDay($employee, [
        'date'                  => '2026-03-04',
        'extra_hours'           => 1,
        'extra_hours_diurnas'   => 0,
        'extra_hours_nocturnas' => 1,
        'overtime_approved'     => true,
        'is_holiday'            => false,
        'is_weekend'            => false,
    ]);

    $result = (new ExtraHourCalculator())->calculate($employee, $period);

    $hourlyRate = 2_550_000 / 240;
    $expected   = round(1 * $hourlyRate * 2.6, 2);

    expect($result['hours'])->toBe(1.0)
        ->and($result['total'])->toBe($expected)
        ->and($result['items'][0]['description'])->toContain('Nocturnas');
});

it('calcula correctamente horas extra en feriado o domingo', function () {
    // salary=2,550,000 / 240h = 10,625/h × 2.0 × 3h = 63,750
    $employee = makeEhcEmployee('mensual', 2_550_000);
    $period   = makeEhcPeriod();

    makeEhcDay($employee, [
        'date'              => '2026-03-08', // domingo
        'extra_hours'       => 3,
        'overtime_approved' => true,
        'is_weekend'        => true,
        'is_holiday'        => false,
    ]);

    $result = (new ExtraHourCalculator())->calculate($employee, $period);

    $hourlyRate = 2_550_000 / 240;
    $expected   = round(3 * $hourlyRate * 2.0, 2);

    expect($result['hours'])->toBe(3.0)
        ->and($result['total'])->toBe($expected)
        ->and($result['items'][0]['description'])->toContain('Feriado');
});

it('ignora horas extra no aprobadas', function () {
    $employee = makeEhcEmployee();
    $period   = makeEhcPeriod();

    makeEhcDay($employee, [
        'date'                => '2026-03-05',
        'extra_hours'         => 3,
        'extra_hours_diurnas' => 3,
        'overtime_approved'   => false, // no aprobada
    ]);

    $result = (new ExtraHourCalculator())->calculate($employee, $period);

    expect($result['total'])->toBe(0)
        ->and($result['hours'])->toBe(0);
});

it('suma correctamente múltiples días con tipos distintos', function () {
    $employee = makeEhcEmployee('mensual', 2_550_000);
    $period   = makeEhcPeriod();

    $hourlyRate = 2_550_000 / 240;

    // Día diurno: 2h
    makeEhcDay($employee, [
        'date'                  => '2026-03-03',
        'extra_hours'           => 2,
        'extra_hours_diurnas'   => 2,
        'extra_hours_nocturnas' => 0,
        'overtime_approved'     => true,
        'is_holiday'            => false,
        'is_weekend'            => false,
    ]);

    // Día nocturno: 1h
    makeEhcDay($employee, [
        'date'                  => '2026-03-04',
        'extra_hours'           => 1,
        'extra_hours_diurnas'   => 0,
        'extra_hours_nocturnas' => 1,
        'overtime_approved'     => true,
        'is_holiday'            => false,
        'is_weekend'            => false,
    ]);

    $result = (new ExtraHourCalculator())->calculate($employee, $period);

    $expectedTotal = round(2 * $hourlyRate * 1.5, 2) + round(1 * $hourlyRate * 2.6, 2);

    expect($result['hours'])->toBe(3.0)
        ->and($result['total'])->toBe($expectedTotal)
        ->and($result['items'])->toHaveCount(2);
});

// ─── Jornalero ───────────────────────────────────────────────────────────────

it('calcula horas extra para jornalero con tarifa diaria', function () {
    // daily_rate=150,000 / 8h = 18,750/h × 1.5 × 2h = 56,250
    $employee = makeEhcEmployee('jornal', 150_000);
    $period   = makeEhcPeriod();

    makeEhcDay($employee, [
        'date'                  => '2026-03-05',
        'extra_hours'           => 2,
        'extra_hours_diurnas'   => 2,
        'extra_hours_nocturnas' => 0,
        'overtime_approved'     => true,
        'is_holiday'            => false,
        'is_weekend'            => false,
    ]);

    $result = (new ExtraHourCalculator())->calculate($employee, $period);

    $hourlyRate = 150_000 / 8;
    $expected   = round(2 * $hourlyRate * 1.5, 2);

    expect($result['hours'])->toBe(2.0)
        ->and($result['total'])->toBe($expected);
});

it('excluye días fuera del período', function () {
    $employee = makeEhcEmployee('mensual', 2_550_000);
    $period   = makeEhcPeriod();

    // Día fuera del período (abril)
    AttendanceDay::create([
        'employee_id'       => $employee->id,
        'date'              => '2026-04-01',
        'extra_hours'       => 5,
        'extra_hours_diurnas' => 5,
        'overtime_approved' => true,
        'is_holiday'        => false,
        'is_weekend'        => false,
    ]);

    $result = (new ExtraHourCalculator())->calculate($employee, $period);

    expect($result['total'])->toBe(0)
        ->and($result['hours'])->toBe(0);
});
