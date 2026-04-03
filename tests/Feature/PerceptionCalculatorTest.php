<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeePerception;
use App\Models\PayrollPeriod;
use App\Models\Perception;
use App\Models\Position;
use App\Services\PerceptionCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * Crea un empleado mensual o jornalero con contrato activo para tests de PerceptionCalculator.
 *
 * @param  string  $salaryType  'mensual' | 'jornal'
 * @param  int     $salary      Monto del salario
 */
function makePercEmployee(string $salaryType = 'mensual', int $salary = 2_550_000): Employee
{
    static $ci = 7000000;
    $n = $ci++;

    $company    = Company::create(['name' => "EmpP {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch     = Branch::create(['name' => "SucP {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "DepP {$n}", 'company_id' => $company->id]);
    $position   = Position::create(['name' => "PosP {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Test',
        'last_name'  => 'Perc',
        'ci'         => (string) $n,
        'email'      => "perc{$n}@test.com",
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
function makePercPeriod(int $month = 3): PayrollPeriod
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
 * Crea una percepción de monto fijo.
 */
function makeFixedPerception(string $suffix = '', int $amount = 500_000): Perception
{
    static $code = 1;
    return Perception::create([
        'name'        => "Bono fijo {$suffix}",
        'code'        => 'PF' . ($code++),
        'calculation' => 'fixed',
        'amount'      => $amount,
        'is_active'   => true,
    ]);
}

/**
 * Crea una percepción por porcentaje.
 */
function makePercentPerception(string $suffix = '', float $percent = 5.00): Perception
{
    static $code = 1;
    return Perception::create([
        'name'        => "Bono % {$suffix}",
        'code'        => 'PP' . ($code++),
        'calculation' => 'percentage',
        'percent'     => $percent,
        'is_active'   => true,
    ]);
}

/**
 * Asigna una percepción a un empleado en el rango de fechas dado.
 */
function assignPerception(
    Employee $employee,
    Perception $perception,
    string $startDate = '2026-01-01',
    ?string $endDate = null,
    ?int $customAmount = null,
): void {
    EmployeePerception::create([
        'employee_id'   => $employee->id,
        'perception_id' => $perception->id,
        'start_date'    => $startDate,
        'end_date'      => $endDate,
        'custom_amount' => $customAmount,
    ]);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('retorna total 0 e items vacíos si el empleado no tiene percepciones asignadas', function () {
    $employee = makePercEmployee();
    $period   = makePercPeriod();

    $result = app(PerceptionCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe(0)
        ->and($result['items'])->toBeEmpty();
});

it('calcula correctamente una percepción de monto fijo', function () {
    $employee   = makePercEmployee();
    $period     = makePercPeriod();
    $perception = makeFixedPerception(amount: 500_000);

    assignPerception($employee, $perception);

    $result = app(PerceptionCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe(500_000)
        ->and($result['items'])->toHaveCount(1)
        ->and($result['items'][0])->toMatchArray([
            'description' => $perception->name,
            'amount'      => 500_000,
        ]);
});

it('calcula percepción por porcentaje usando base_salary para empleado mensual', function () {
    $salary   = 2_550_000;
    $percent  = 5.00;
    $expected = (int) round($salary * $percent / 100); // 127500

    $employee   = makePercEmployee('mensual', $salary);
    $period     = makePercPeriod();
    $perception = makePercentPerception(percent: $percent);

    assignPerception($employee, $perception);

    $result = app(PerceptionCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe($expected)
        ->and($result['items'][0]['amount'])->toBe($expected);
});

it('calcula percepción por porcentaje usando daily_rate para jornalero', function () {
    $dailyRate = 120_000;
    $percent   = 10.00;
    $expected  = (int) round($dailyRate * $percent / 100); // 12000

    $employee   = makePercEmployee('jornal', $dailyRate);
    $period     = makePercPeriod();
    $perception = makePercentPerception(percent: $percent);

    assignPerception($employee, $perception);

    $result = app(PerceptionCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe($expected)
        ->and($result['items'][0]['amount'])->toBe($expected);
});

it('usa custom_amount cuando está definido, ignorando el cálculo normal', function () {
    $customAmount = 300_000;

    $employee   = makePercEmployee('mensual', 2_550_000);
    $period     = makePercPeriod();
    $perception = makePercentPerception(percent: 5.00);

    assignPerception($employee, $perception, customAmount: $customAmount);

    $result = app(PerceptionCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe($customAmount)
        ->and($result['items'][0]['amount'])->toBe($customAmount);
});

it('retorna amount=0 para percepción porcentual si el empleado no tiene salario base', function () {
    // Empleado jornalero sin daily_rate (salary=0 en el contrato)
    $employee   = makePercEmployee('jornal', 0);
    $period     = makePercPeriod();
    $perception = makePercentPerception(percent: 10.00);

    assignPerception($employee, $perception);

    $result = app(PerceptionCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe(0)
        ->and($result['items'])->toHaveCount(1)
        ->and($result['items'][0]['amount'])->toBe(0);
});

it('suma correctamente múltiples percepciones asignadas', function () {
    $employee = makePercEmployee('mensual', 2_000_000);
    $period   = makePercPeriod();

    $fixed1 = makeFixedPerception(amount: 200_000);
    $fixed2 = makeFixedPerception(amount: 300_000);

    assignPerception($employee, $fixed1);
    assignPerception($employee, $fixed2);

    $result = app(PerceptionCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe(500_000)
        ->and($result['items'])->toHaveCount(2);
});

it('excluye percepciones cuya end_date es anterior al inicio del período', function () {
    $employee   = makePercEmployee();
    $period     = makePercPeriod(month: 3); // 2026-03-01 – 2026-03-31
    $perception = makeFixedPerception(amount: 500_000);

    // end_date = 2026-02-28: terminó antes de que empiece marzo
    assignPerception($employee, $perception, startDate: '2026-01-01', endDate: '2026-02-28');

    $result = app(PerceptionCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe(0)
        ->and($result['items'])->toBeEmpty();
});

it('excluye percepciones cuya start_date es posterior al fin del período', function () {
    $employee   = makePercEmployee();
    $period     = makePercPeriod(month: 3); // 2026-03-01 – 2026-03-31
    $perception = makeFixedPerception(amount: 500_000);

    // start_date = 2026-04-01: inicia después de que termine marzo
    assignPerception($employee, $perception, startDate: '2026-04-01');

    $result = app(PerceptionCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe(0)
        ->and($result['items'])->toBeEmpty();
});

it('incluye percepción cuya vigencia se superpone parcialmente con el período', function () {
    $employee   = makePercEmployee();
    $period     = makePercPeriod(month: 3); // 2026-03-01 – 2026-03-31
    $perception = makeFixedPerception(amount: 500_000);

    // start_date = 2026-03-15, end_date = 2026-04-15: activa durante parte de marzo
    assignPerception($employee, $perception, startDate: '2026-03-15', endDate: '2026-04-15');

    $result = app(PerceptionCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe(500_000)
        ->and($result['items'])->toHaveCount(1);
});

it('incluye percepción sin fecha de fin (vigencia indefinida)', function () {
    $employee   = makePercEmployee();
    $period     = makePercPeriod();
    $perception = makeFixedPerception(amount: 150_000);

    assignPerception($employee, $perception, endDate: null);

    $result = app(PerceptionCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe(150_000);
});
