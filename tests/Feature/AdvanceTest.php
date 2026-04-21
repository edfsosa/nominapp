<?php

use App\Models\Advance;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Crea un empleado con contrato activo para tests de Advance.
 */
function makeAdvEmployee(int $salary = 2_550_000): Employee
{
    static $ci = 7000000;
    $n = $ci++;

    $company = Company::create(['name' => "EmpAdv {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch = Branch::create(['name' => "SucAdv {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "DepAdv {$n}", 'company_id' => $company->id]);
    $position = Position::create(['name' => "PosAdv {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Test',
        'last_name' => 'Advance',
        'ci' => (string) $n,
        'email' => "adv{$n}@test.com",
        'branch_id' => $branch->id,
        'status' => 'active',
    ]);

    Contract::create([
        'employee_id' => $employee->id,
        'type' => 'indefinido',
        'start_date' => Carbon::now()->subYear(),
        'salary_type' => 'mensual',
        'salary' => $salary,
        'payroll_type' => 'monthly',
        'position_id' => $position->id,
        'department_id' => $department->id,
        'status' => 'active',
    ]);

    return $employee->fresh();
}

/** Crea un adelanto para el empleado dado. */
function makeAdvance(Employee $employee, float $amount = 500_000, string $status = 'pending'): Advance
{
    return Advance::create([
        'employee_id' => $employee->id,
        'amount' => $amount,
        'status' => $status,
    ]);
}

/** Crea un PayrollPeriod mensual de referencia para tests de Advance. */
function makeAdvPeriod(int $month = 3): PayrollPeriod
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

/** Crea un Payroll mínimo para tests que requieren un payroll_id. */
function makeAdvPayroll(Employee $employee, PayrollPeriod $period): Payroll
{
    return Payroll::create([
        'employee_id' => $employee->id,
        'payroll_period_id' => $period->id,
        'status' => 'draft',
        'gross_salary' => 2_550_000,
        'net_salary' => 2_550_000,
        'total_deductions' => 0,
        'total_perceptions' => 0,
    ]);
}

/** Obtiene o crea el usuario admin para tests de Advance. */
function getAdvAdmin(): User
{
    return User::firstOrCreate(
        ['email' => 'adv_admin@test.com'],
        ['name' => 'Adv Admin', 'password' => bcrypt('password')],
    );
}

// ─── approve() ───────────────────────────────────────────────────────────────

it('aprueba un adelanto pendiente correctamente', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee);
    $admin = getAdvAdmin();

    $result = $advance->approve($admin->id);

    expect($result['success'])->toBeTrue();

    $advance->refresh();
    expect($advance->status)->toBe('approved')
        ->and($advance->approved_by_id)->toBe($admin->id)
        ->and($advance->approved_at)->not->toBeNull();
});

it('falla al aprobar si el adelanto no está pendiente', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'approved');

    $result = $advance->approve(getAdvAdmin()->id);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Pendiente');
});

it('falla al aprobar si el empleado no tiene contrato activo', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee);

    $employee->activeContract->delete();

    $result = $advance->fresh()->approve(getAdvAdmin()->id);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('contrato activo');
});

it('permite aprobar múltiples adelantos para el mismo empleado', function () {
    $employee = makeAdvEmployee();
    $first = makeAdvance($employee);
    $second = makeAdvance($employee);

    $resultFirst = $first->approve(getAdvAdmin()->id);
    $resultSecond = $second->approve(getAdvAdmin()->id);

    expect($resultFirst['success'])->toBeTrue()
        ->and($resultSecond['success'])->toBeTrue();
});

// ─── reject() ────────────────────────────────────────────────────────────────

it('rechaza un adelanto pendiente', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee);

    $result = $advance->reject('No cumple requisitos');

    expect($result['success'])->toBeTrue();

    $advance->refresh();
    expect($advance->status)->toBe('rejected')
        ->and($advance->notes)->toContain('No cumple requisitos');
});

it('falla al rechazar si el adelanto no está pendiente', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'approved');

    $result = $advance->reject();

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Pendiente');
});

it('rechaza sin motivo sin error', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee);

    $result = $advance->reject();

    expect($result['success'])->toBeTrue();
    expect($advance->fresh()->status)->toBe('rejected');
});

// ─── cancel() ────────────────────────────────────────────────────────────────

it('cancela un adelanto pendiente', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee);

    $result = $advance->cancel('Solicitud del empleado');

    expect($result['success'])->toBeTrue();

    $advance->refresh();
    expect($advance->status)->toBe('cancelled')
        ->and($advance->notes)->toContain('Solicitud del empleado');
});

it('cancela un adelanto aprobado aún no procesado en nómina', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'approved');

    $result = $advance->cancel();

    expect($result['success'])->toBeTrue();
    expect($advance->fresh()->status)->toBe('cancelled');
});

it('falla al cancelar un adelanto ya pagado', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'paid');

    $result = $advance->cancel();

    expect($result['success'])->toBeFalse();
});

it('falla al cancelar un adelanto ya rechazado', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'rejected');

    $result = $advance->cancel();

    expect($result['success'])->toBeFalse();
});

it('falla al cancelar si el adelanto ya fue procesado en nómina', function () {
    $employee = makeAdvEmployee();
    $period = makeAdvPeriod();
    $payroll = makeAdvPayroll($employee, $period);

    $advance = Advance::create([
        'employee_id' => $employee->id,
        'amount' => 500_000,
        'status' => 'approved',
        'payroll_id' => $payroll->id,
    ]);

    $result = $advance->cancel();

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('nómina');
});

// ─── markAsPaid() ─────────────────────────────────────────────────────────────

it('marca el adelanto como pagado con el payroll_id correcto', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'approved');
    $period = makeAdvPeriod(month: 4);
    $payroll = makeAdvPayroll($employee, $period);

    $advance->markAsPaid($payroll->id);

    $advance->refresh();
    expect($advance->status)->toBe('paid')
        ->and($advance->payroll_id)->toBe($payroll->id);
});

// ─── getActiveForEmployee() ───────────────────────────────────────────────────

it('getActiveForEmployee retorna adelanto pending', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'pending');

    expect(Advance::getActiveForEmployee($employee->id)?->id)->toBe($advance->id);
});

it('getActiveForEmployee retorna adelanto approved', function () {
    $employee = makeAdvEmployee();
    $advance = makeAdvance($employee, status: 'approved');

    expect(Advance::getActiveForEmployee($employee->id)?->id)->toBe($advance->id);
});

it('getActiveForEmployee retorna null si solo hay adelantos en estado final', function () {
    $employee = makeAdvEmployee();
    makeAdvance($employee, status: 'paid');
    makeAdvance($employee, status: 'rejected');
    makeAdvance($employee, status: 'cancelled');

    expect(Advance::getActiveForEmployee($employee->id))->toBeNull();
});

it('getActiveForEmployee retorna null si no hay adelantos', function () {
    $employee = makeAdvEmployee();

    expect(Advance::getActiveForEmployee($employee->id))->toBeNull();
});

// ─── getPendingCount() ───────────────────────────────────────────────────────

it('getPendingCount retorna el número de adelantos pendientes', function () {
    $emp1 = makeAdvEmployee();
    $emp2 = makeAdvEmployee();

    makeAdvance($emp1, status: 'pending');
    makeAdvance($emp2, status: 'pending');
    makeAdvance($emp1, status: 'approved');

    expect(Advance::getPendingCount())->toBe(2);
});
