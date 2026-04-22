<?php

use App\Models\Advance;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Deduction;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Services\AdvanceCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Crea la deducción ADE001 requerida por AdvanceCalculator.
 */
function seedAdvanceDeduction(): void
{
    Deduction::firstOrCreate(['code' => 'ADE001'], [
        'name' => 'Adelanto de Salario',
        'type' => 'loan',
        'calculation' => 'fixed',
        'is_mandatory' => false,
        'is_active' => true,
        'affects_irp' => false,
    ]);
}

/**
 * Crea un empleado mensualizado con contrato activo para tests de AdvanceCalculator.
 */
function makeAdvCalcEmployee(): Employee
{
    static $ci = 5000000;
    $n = $ci++;

    $company = Company::create(['name' => "EmpAC {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch = Branch::create(['name' => "SucAC {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "DepAC {$n}", 'company_id' => $company->id]);
    $position = Position::create(['name' => "PosAC {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Calc',
        'last_name' => 'Advance',
        'ci' => (string) $n,
        'email' => "ac{$n}@test.com",
        'branch_id' => $branch->id,
        'status' => 'active',
    ]);

    Contract::create([
        'employee_id' => $employee->id,
        'type' => 'indefinido',
        'start_date' => Carbon::now()->subYear(),
        'salary_type' => 'mensual',
        'salary' => 2_550_000,
        'payroll_type' => 'monthly',
        'position_id' => $position->id,
        'department_id' => $department->id,
        'status' => 'active',
    ]);

    return $employee->fresh();
}

/**
 * Crea un PayrollPeriod mensual para el mes indicado de 2026.
 */
function makeAdvCalcPeriod(int $month = 3): PayrollPeriod
{
    $start = Carbon::create(2026, $month, 1);

    return PayrollPeriod::create([
        'name' => $start->format('F Y'),
        'start_date' => $start->toDateString(),
        'end_date' => $start->copy()->endOfMonth()->toDateString(),
        'frequency' => 'monthly',
        'status' => 'draft',
    ]);
}

/**
 * Crea un adelanto aprobado listo para descuento en nómina.
 */
function makeApprovedAdvance(Employee $employee, float $amount = 500_000, ?string $approvedAt = null): Advance
{
    return Advance::create([
        'employee_id' => $employee->id,
        'amount' => $amount,
        'status' => 'approved',
        'approved_at' => $approvedAt ?? Carbon::create(2026, 3, 1)->toDateTimeString(),
    ]);
}

// ─── calculate() ─────────────────────────────────────────────────────────────

it('retorna advances vacía si el empleado no tiene adelantos aprobados', function () {
    seedAdvanceDeduction();
    $employee = makeAdvCalcEmployee();
    $period = makeAdvCalcPeriod();

    $result = app(AdvanceCalculator::class)->calculate($employee, $period);

    expect($result['advances'])->toBeEmpty();
    expect(EmployeeDeduction::count())->toBe(0);
});

it('crea EmployeeDeduction con ADE001 para adelanto aprobado dentro del período', function () {
    seedAdvanceDeduction();
    $employee = makeAdvCalcEmployee();
    $period = makeAdvCalcPeriod(month: 3);
    $advance = makeApprovedAdvance($employee, 500_000, '2026-03-15 10:00:00');

    $result = app(AdvanceCalculator::class)->calculate($employee, $period);

    expect($result['advances'])->toHaveCount(1);

    $ed = EmployeeDeduction::first();
    expect($ed)->not->toBeNull()
        ->and((float) $ed->custom_amount)->toBe(500_000.0)
        ->and($ed->notes)->toContain('Adelanto');

    // El adelanto debe referenciar el EmployeeDeduction creado
    expect($advance->fresh()->employee_deduction_id)->toBe($ed->id);
});

it('no procesa adelantos en estado pending (solo approved)', function () {
    seedAdvanceDeduction();
    $employee = makeAdvCalcEmployee();
    $period = makeAdvCalcPeriod();

    Advance::create([
        'employee_id' => $employee->id,
        'amount' => 500_000,
        'status' => 'pending',
    ]);

    $result = app(AdvanceCalculator::class)->calculate($employee, $period);

    expect($result['advances'])->toBeEmpty();
    expect(EmployeeDeduction::count())->toBe(0);
});

it('no procesa adelantos que ya tienen payroll_id asignado', function () {
    seedAdvanceDeduction();
    $employee = makeAdvCalcEmployee();
    $period = makeAdvCalcPeriod();

    $payroll = Payroll::create([
        'employee_id' => $employee->id,
        'payroll_period_id' => $period->id,
        'status' => 'draft',
        'base_salary' => 2_550_000,
        'gross_salary' => 2_550_000,
        'net_salary' => 2_550_000,
        'total_deductions' => 0,
        'total_perceptions' => 0,
    ]);

    Advance::create([
        'employee_id' => $employee->id,
        'amount' => 500_000,
        'status' => 'approved',
        'approved_at' => '2026-03-01 08:00:00',
        'payroll_id' => $payroll->id,
    ]);

    $result = app(AdvanceCalculator::class)->calculate($employee, $period);

    expect($result['advances'])->toBeEmpty();
    expect(EmployeeDeduction::count())->toBe(0);
});

it('excluye adelantos con approved_at posterior al fin del período', function () {
    seedAdvanceDeduction();
    $employee = makeAdvCalcEmployee();
    $period = makeAdvCalcPeriod(month: 3); // hasta 2026-03-31

    // Aprobado el 1° de abril → fuera del período
    makeApprovedAdvance($employee, 500_000, '2026-04-01 08:00:00');

    $result = app(AdvanceCalculator::class)->calculate($employee, $period);

    expect($result['advances'])->toBeEmpty();
    expect(EmployeeDeduction::count())->toBe(0);
});

it('es idempotente: una segunda llamada no duplica EmployeeDeduction', function () {
    seedAdvanceDeduction();
    $employee = makeAdvCalcEmployee();
    $period = makeAdvCalcPeriod();
    makeApprovedAdvance($employee);

    $calc = app(AdvanceCalculator::class);
    $calc->calculate($employee, $period);
    $calc->calculate($employee, $period);

    expect(EmployeeDeduction::count())->toBe(1);
});

it('actualiza el monto en EmployeeDeduction existente al recalcular', function () {
    seedAdvanceDeduction();
    $employee = makeAdvCalcEmployee();
    $period = makeAdvCalcPeriod();
    $advance = makeApprovedAdvance($employee, 500_000);

    $calc = app(AdvanceCalculator::class);
    $calc->calculate($employee, $period);

    // Simular cambio de monto antes de la segunda llamada
    $advance->update(['amount' => 800_000]);

    $calc->calculate($employee, $period);

    expect(EmployeeDeduction::count())->toBe(1);
    expect((float) EmployeeDeduction::first()->custom_amount)->toBe(800_000.0);
});

it('retorna advances vacía y no genera EmployeeDeduction si ADE001 no existe', function () {
    // Sin seedAdvanceDeduction()
    $employee = makeAdvCalcEmployee();
    $period = makeAdvCalcPeriod();
    makeApprovedAdvance($employee);

    $result = app(AdvanceCalculator::class)->calculate($employee, $period);

    expect($result['advances'])->toBeEmpty();
    expect(EmployeeDeduction::count())->toBe(0);
});

it('no procesa adelantos de otro empleado', function () {
    seedAdvanceDeduction();
    $employee1 = makeAdvCalcEmployee();
    $employee2 = makeAdvCalcEmployee();
    $period = makeAdvCalcPeriod();

    makeApprovedAdvance($employee2);

    $result = app(AdvanceCalculator::class)->calculate($employee1, $period);

    expect($result['advances'])->toBeEmpty();
    expect(EmployeeDeduction::count())->toBe(0);
});

// ─── markAdvancesAsPaid() ────────────────────────────────────────────────────

it('retorna 0 si la lista de IDs está vacía', function () {
    $count = app(AdvanceCalculator::class)->markAdvancesAsPaid([], 1);

    expect($count)->toBe(0);
});

it('marca adelantos aprobados como pagados y retorna el conteo', function () {
    $employee = makeAdvCalcEmployee();
    $period = makeAdvCalcPeriod();
    $payroll = Payroll::create([
        'employee_id' => $employee->id,
        'payroll_period_id' => $period->id,
        'status' => 'draft',
        'base_salary' => 2_550_000,
        'gross_salary' => 2_550_000,
        'net_salary' => 2_550_000,
        'total_deductions' => 0,
        'total_perceptions' => 0,
    ]);

    $adv1 = makeApprovedAdvance($employee, 500_000);
    $adv2 = makeApprovedAdvance($employee, 300_000);

    $count = app(AdvanceCalculator::class)->markAdvancesAsPaid([$adv1->id, $adv2->id], $payroll->id);

    expect($count)->toBe(2)
        ->and($adv1->fresh()->status)->toBe('paid')
        ->and($adv1->fresh()->payroll_id)->toBe($payroll->id)
        ->and($adv2->fresh()->status)->toBe('paid');
});

it('ignora adelantos que no están en estado approved al marcar como pagados', function () {
    $employee = makeAdvCalcEmployee();
    $period = makeAdvCalcPeriod();
    $payroll = Payroll::create([
        'employee_id' => $employee->id,
        'payroll_period_id' => $period->id,
        'status' => 'draft',
        'base_salary' => 2_550_000,
        'gross_salary' => 2_550_000,
        'net_salary' => 2_550_000,
        'total_deductions' => 0,
        'total_perceptions' => 0,
    ]);

    $approved = makeApprovedAdvance($employee);
    $pending = Advance::create([
        'employee_id' => $employee->id,
        'amount' => 300_000,
        'status' => 'pending',
    ]);

    $count = app(AdvanceCalculator::class)->markAdvancesAsPaid([$approved->id, $pending->id], $payroll->id);

    // Solo el approved cuenta
    expect($count)->toBe(1)
        ->and($pending->fresh()->status)->toBe('pending');
});
