<?php

use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Services\RestDayCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeRdEmployee(string $employmentType = 'day_laborer', float $dailyRate = 87_950): Employee
{
    static $ci = 5_000_000;
    $n = $ci++;

    $company    = Company::create(['name' => "EmpRD {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch     = Branch::create(['name' => "SucRD {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "DepRD {$n}", 'company_id' => $company->id]);
    $position   = Position::create(['name' => "PosRD {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name'      => 'Test',
        'last_name'       => 'RestDay',
        'ci'              => (string) $n,
        'email'           => "rd{$n}@test.com",
        'branch_id'       => $branch->id,
        'status'          => 'active',
        'employment_type' => $employmentType,
        'daily_rate'      => $dailyRate,
    ]);

    Contract::create([
        'employee_id'   => $employee->id,
        'type'          => 'indefinido',
        'start_date'    => Carbon::now()->subYear(),
        'salary_type'   => 'jornal',
        'salary'        => $dailyRate,
        'payroll_type'  => 'monthly',
        'position_id'   => $position->id,
        'department_id' => $department->id,
        'status'        => 'active',
    ]);

    return $employee->fresh();
}

/**
 * Período mensual de marzo 2026 (lun 2 — mar 31).
 */
function makeRdPeriod(): PayrollPeriod
{
    return PayrollPeriod::create([
        'name'       => 'Marzo 2026',
        'start_date' => '2026-03-01',
        'end_date'   => '2026-03-31',
        'frequency'  => 'monthly',
        'status'     => 'draft',
    ]);
}

/**
 * Registra días presentes para un empleado.
 *
 * @param Employee $employee
 * @param string[] $dates  Fechas en formato 'Y-m-d'
 */
function markPresent(Employee $employee, array $dates): void
{
    foreach ($dates as $date) {
        AttendanceDay::create([
            'employee_id' => $employee->id,
            'date'        => $date,
            'status'      => 'present',
        ]);
    }
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('retorna total=0 e items=[] para empleado mensualado', function () {
    $employee = makeRdEmployee('mensualado');
    $period   = makeRdPeriod();

    $result = (new RestDayCalculator)->calculate($employee, $period);

    expect($result['total'])->toBe(0.0)
        ->and($result['items'])->toBeEmpty();
});

it('retorna total=0 si el jornalero no tiene días presentes en el período', function () {
    $employee = makeRdEmployee();
    $period   = makeRdPeriod();

    $result = (new RestDayCalculator)->calculate($employee, $period);

    expect($result['total'])->toBe(0.0)
        ->and($result['items'])->toBeEmpty();
});

it('calcula descanso correcto para una semana con 6 días trabajados', function () {
    // Semana ISO 10 de 2026: lun 2 — dom 8 de marzo
    $employee = makeRdEmployee(dailyRate: 87_950);
    $period   = makeRdPeriod();

    markPresent($employee, ['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05', '2026-03-06', '2026-03-07']);

    $result = (new RestDayCalculator)->calculate($employee, $period);

    // 6 × 87.950 / 6 = 87.950
    expect($result['total'])->toBe(87_950.0)
        ->and($result['items'])->toHaveCount(1)
        ->and($result['items'][0]['description'])->toBe('Descanso Semanal Remunerado')
        ->and($result['items'][0]['perception_type'])->toBe('salary');
});

it('calcula descanso correcto para una semana con 5 días trabajados', function () {
    // 5 días → descanso = 5 × 87.950 / 6 = 73.291,67 → redondeado
    $employee = makeRdEmployee(dailyRate: 87_950);
    $period   = makeRdPeriod();

    markPresent($employee, ['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05', '2026-03-06']);

    $result = (new RestDayCalculator)->calculate($employee, $period);

    $expected = round(5 * 87_950 / 6, 2);

    expect($result['total'])->toBe($expected);
});

it('capea en 6 días aunque el jornalero haya trabajado 7 días en la semana', function () {
    // Semana completa lun-dom = 7 días; el cálculo debe usar min(7, 6) = 6
    $employee = makeRdEmployee(dailyRate: 87_950);
    $period   = makeRdPeriod();

    markPresent($employee, ['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05', '2026-03-06', '2026-03-07', '2026-03-08']);

    $result = (new RestDayCalculator)->calculate($employee, $period);

    $expectedSingleWeek = round(6 * 87_950 / 6, 2); // = 87.950

    expect($result['total'])->toBe($expectedSingleWeek);
});

it('suma el descanso de múltiples semanas correctamente', function () {
    // Semana 10 (2-6 mar): 5 días
    // Semana 11 (9-13 mar): 6 días
    $employee = makeRdEmployee(dailyRate: 87_950);
    $period   = makeRdPeriod();

    markPresent($employee, [
        '2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05', '2026-03-06', // semana 10
        '2026-03-09', '2026-03-10', '2026-03-11', '2026-03-12', '2026-03-13', '2026-03-14', // semana 11
    ]);

    $result = (new RestDayCalculator)->calculate($employee, $period);

    $week10 = round(5 * 87_950 / 6, 2);
    $week11 = round(6 * 87_950 / 6, 2);
    $expected = round($week10 + $week11, 2);

    expect($result['total'])->toBe($expected);
});

it('retorna exactamente un ítem aunque haya varias semanas', function () {
    $employee = makeRdEmployee();
    $period   = makeRdPeriod();

    markPresent($employee, [
        '2026-03-02', '2026-03-03',
        '2026-03-09', '2026-03-10',
        '2026-03-16', '2026-03-17',
    ]);

    $result = (new RestDayCalculator)->calculate($employee, $period);

    expect($result['items'])->toHaveCount(1);
});

it('excluye días presentes fuera del período', function () {
    $employee = makeRdEmployee(dailyRate: 87_950);
    $period   = makeRdPeriod(); // marzo 2026

    // Días de febrero (fuera del período) + 5 días de marzo
    markPresent($employee, [
        '2026-02-23', '2026-02-24', '2026-02-25',         // fuera del período
        '2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05', '2026-03-06', // dentro
    ]);

    $result = (new RestDayCalculator)->calculate($employee, $period);

    // Solo la semana de marzo (5 días)
    $expected = round(5 * 87_950 / 6, 2);

    expect($result['total'])->toBe($expected);
});

it('retorna total=0 si el jornalero no tiene daily_rate', function () {
    $employee = makeRdEmployee(dailyRate: 0);
    $period   = makeRdPeriod();

    markPresent($employee, ['2026-03-02', '2026-03-03']);

    $result = (new RestDayCalculator)->calculate($employee, $period);

    expect($result['total'])->toBe(0.0)
        ->and($result['items'])->toBeEmpty();
});
