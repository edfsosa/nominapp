<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Deduction;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Services\DeductionCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * Crea un empleado mensual o jornalero con contrato activo para tests de DeductionCalculator.
 *
 * @param  string  $salaryType  'mensual' | 'jornal'
 * @param  int     $salary      Monto del salario
 */
function makeDedEmployee(string $salaryType = 'mensual', int $salary = 2_550_000): Employee
{
    static $ci = 8000000;
    $n = $ci++;

    $company    = Company::create(['name' => "EmpD {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch     = Branch::create(['name' => "SucD {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "DepD {$n}", 'company_id' => $company->id]);
    $position   = Position::create(['name' => "PosD {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Test',
        'last_name'  => 'Ded',
        'ci'         => (string) $n,
        'email'      => "ded{$n}@test.com",
        'branch_id'  => $branch->id,
        'status'     => 'active',
    ]);

    Contract::create([
        'employee_id'   => $employee->id,
        'type'          => 'indefinido',
        'start_date'    => Carbon::now()->subYear(),
        'salary_type'   => $salaryType,
        'salary'        => $salary,
        'payroll_type'  => 'monthly',
        'position_id'   => $position->id,
        'department_id' => $department->id,
        'status'        => 'active',
    ]);

    return $employee->fresh();
}

/**
 * Crea un PayrollPeriod mensual para el mes indicado de 2026.
 */
function makeDedPeriod(int $month = 3): PayrollPeriod
{
    $start = Carbon::create(2026, $month, 1);

    return PayrollPeriod::create([
        'name'       => $start->format('F Y'),
        'start_date' => $start->toDateString(),
        'end_date'   => $start->copy()->endOfMonth()->toDateString(),
        'frequency'  => 'monthly',
        'status'     => 'draft',
    ]);
}

/**
 * Crea una deducción de monto fijo.
 */
function makeFixedDeduction(string $suffix = '', float $amount = 200_000): Deduction
{
    static $code = 1;
    return Deduction::create([
        'name'        => "Descuento fijo {$suffix}",
        'code'        => 'DF' . ($code++),
        'calculation' => 'fixed',
        'amount'      => $amount,
        'is_active'   => true,
    ]);
}

/**
 * Crea una deducción por porcentaje.
 */
function makePercentDeduction(string $suffix = '', float $percent = 9.00): Deduction
{
    static $code = 1;
    return Deduction::create([
        'name'        => "Descuento % {$suffix}",
        'code'        => 'DP' . ($code++),
        'calculation' => 'percentage',
        'percent'     => $percent,
        'is_active'   => true,
    ]);
}

/**
 * Asigna una deducción a un empleado en el rango de fechas dado.
 */
function assignDeduction(
    Employee $employee,
    Deduction $deduction,
    string $startDate = '2026-01-01',
    ?string $endDate = null,
    ?float $customAmount = null,
): void {
    EmployeeDeduction::create([
        'employee_id'   => $employee->id,
        'deduction_id'  => $deduction->id,
        'start_date'    => $startDate,
        'end_date'      => $endDate,
        'custom_amount' => $customAmount,
    ]);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('retorna total 0 e items vacíos si el empleado no tiene deducciones asignadas', function () {
    $employee = makeDedEmployee();
    $period   = makeDedPeriod();

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe(0)
        ->and($result['items'])->toBeEmpty();
});

it('calcula correctamente una deducción de monto fijo', function () {
    $employee  = makeDedEmployee();
    $period    = makeDedPeriod();
    $deduction = makeFixedDeduction(amount: 200_000);

    assignDeduction($employee, $deduction);

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe(200_000.0)
        ->and($result['items'])->toHaveCount(1)
        ->and($result['items'][0])->toMatchArray([
            'description' => $deduction->name,
            'amount'      => 200_000.0,
        ]);
});

it('calcula deducción por porcentaje usando base_salary para empleado mensual', function () {
    $salary   = 2_550_000;
    $percent  = 9.00;
    $expected = round($salary * $percent / 100, 2); // 229500.0

    $employee  = makeDedEmployee('mensual', $salary);
    $period    = makeDedPeriod();
    $deduction = makePercentDeduction(percent: $percent);

    assignDeduction($employee, $deduction);

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe($expected)
        ->and((float) $result['items'][0]['amount'])->toBe($expected);
});

it('calcula deducción por porcentaje usando daily_rate para jornalero', function () {
    $dailyRate = 120_000;
    $percent   = 9.00;
    $expected  = round($dailyRate * $percent / 100, 2); // 10800.0

    $employee  = makeDedEmployee('jornal', $dailyRate);
    $period    = makeDedPeriod();
    $deduction = makePercentDeduction(percent: $percent);

    assignDeduction($employee, $deduction);

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe($expected)
        ->and((float) $result['items'][0]['amount'])->toBe($expected);
});

it('usa custom_amount cuando está definido, ignorando el cálculo normal', function () {
    $customAmount = 150_000.0;

    $employee  = makeDedEmployee('mensual', 2_550_000);
    $period    = makeDedPeriod();
    $deduction = makePercentDeduction(percent: 9.00);

    assignDeduction($employee, $deduction, customAmount: $customAmount);

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe($customAmount)
        ->and((float) $result['items'][0]['amount'])->toBe($customAmount);
});

it('retorna amount=0 para deducción porcentual si el empleado no tiene salario base', function () {
    // Empleado jornalero con daily_rate = 0
    $employee  = makeDedEmployee('jornal', 0);
    $period    = makeDedPeriod();
    $deduction = makePercentDeduction(percent: 9.00);

    assignDeduction($employee, $deduction);

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe(0)
        ->and($result['items'])->toHaveCount(1)
        ->and((float) $result['items'][0]['amount'])->toBe(0.0);
});

it('suma correctamente múltiples deducciones asignadas', function () {
    $employee = makeDedEmployee('mensual', 2_000_000);
    $period   = makeDedPeriod();

    $ded1 = makeFixedDeduction(amount: 100_000);
    $ded2 = makeFixedDeduction(amount: 150_000);

    assignDeduction($employee, $ded1);
    assignDeduction($employee, $ded2);

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe(250_000.0)
        ->and($result['items'])->toHaveCount(2);
});

it('excluye deducciones cuya end_date es anterior al inicio del período', function () {
    $employee  = makeDedEmployee();
    $period    = makeDedPeriod(month: 3); // 2026-03-01 – 2026-03-31
    $deduction = makeFixedDeduction(amount: 200_000);

    // end_date = 2026-02-28: terminó antes de que empiece marzo
    assignDeduction($employee, $deduction, startDate: '2026-01-01', endDate: '2026-02-28');

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe(0)
        ->and($result['items'])->toBeEmpty();
});

it('excluye deducciones cuya start_date es posterior al fin del período', function () {
    $employee  = makeDedEmployee();
    $period    = makeDedPeriod(month: 3); // 2026-03-01 – 2026-03-31
    $deduction = makeFixedDeduction(amount: 200_000);

    // start_date = 2026-04-01: inicia después de que termine marzo
    assignDeduction($employee, $deduction, startDate: '2026-04-01');

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe(0)
        ->and($result['items'])->toBeEmpty();
});

it('incluye deducción cuya vigencia se superpone parcialmente con el período', function () {
    $employee  = makeDedEmployee();
    $period    = makeDedPeriod(month: 3); // 2026-03-01 – 2026-03-31
    $deduction = makeFixedDeduction(amount: 200_000);

    // start_date = 2026-03-15, end_date = 2026-04-15: activa durante parte de marzo
    assignDeduction($employee, $deduction, startDate: '2026-03-15', endDate: '2026-04-15');

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe(200_000.0)
        ->and($result['items'])->toHaveCount(1);
});

it('incluye deducción sin fecha de fin (vigencia indefinida)', function () {
    $employee  = makeDedEmployee();
    $period    = makeDedPeriod();
    $deduction = makeFixedDeduction(amount: 75_000);

    assignDeduction($employee, $deduction, endDate: null);

    $result = app(DeductionCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe(75_000.0);
});
