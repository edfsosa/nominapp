<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\Position;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Crea un empleado mensualizado con contrato activo.
 *
 * @param  string  $salaryType  'mensual' | 'jornal'
 * @param  int  $salary  Monto del salario
 * @param  string  $payrollType  'monthly' | 'biweekly' | 'weekly'
 */
function makeActivateEmployee(
    string $salaryType = 'mensual',
    int $salary = 2_550_000,
    string $payrollType = 'monthly',
): Employee {
    static $ci = 8000000;
    $n = $ci++;

    $company = Company::create(['name' => "EmpAct {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch = Branch::create(['name' => "SucAct {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "DepAct {$n}", 'company_id' => $company->id]);
    $position = Position::create(['name' => "PosAct {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Test',
        'last_name' => 'Activate',
        'ci' => (string) $n,
        'email' => "act{$n}@test.com",
        'branch_id' => $branch->id,
        'status' => 'active',
    ]);

    Contract::create([
        'employee_id' => $employee->id,
        'type' => 'indefinido',
        'start_date' => Carbon::now()->subYear(),
        'salary_type' => $salaryType,
        'salary' => $salary,
        'payroll_type' => $payrollType,
        'position_id' => $position->id,
        'department_id' => $department->id,
        'status' => 'active',
    ]);

    return $employee->fresh();
}

/**
 * Crea un Loan pendiente listo para activar.
 *
 * @param  float  $interestRate  Tasa de interés anual (0 = sin interés)
 */
function makePendingLoan(
    Employee $employee,
    int $amount = 1_000_000,
    int $installmentsCount = 4,
    float $interestRate = 0.0,
): Loan {
    return Loan::create([
        'employee_id' => $employee->id,
        'amount' => $amount,
        'interest_rate' => $interestRate,
        'installments_count' => $installmentsCount,
        'installment_amount' => 0,
        'status' => 'pending',
        'reason' => 'personal',
    ]);
}

/** Obtiene o crea el usuario admin para tests. */
function getAdminUser(): User
{
    return User::firstOrCreate(
        ['email' => 'admin@test.com'],
        ['name' => 'Admin Test', 'password' => bcrypt('password')],
    );
}

// ─── Validaciones de estado y contrato ───────────────────────────────────────

it('falla si el préstamo no está pendiente', function () {
    $employee = makeActivateEmployee();
    $loan = makePendingLoan($employee);
    $loan->update(['status' => 'active']);

    $result = $loan->activate(getAdminUser()->id);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('pendientes');
});

it('falla si el empleado no tiene contrato activo', function () {
    $employee = makeActivateEmployee();
    $loan = makePendingLoan($employee);

    $employee->activeContract->delete();

    $result = $loan->fresh()->activate(getAdminUser()->id);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('contrato activo');
});

// ─── Validación del límite del 25% ───────────────────────────────────────────

it('falla si la cuota supera el 25% del salario mensual', function () {
    // Salario: 2.000.000 → 25% = 500.000
    // Cuota: 600.000 → debe fallar
    $employee = makeActivateEmployee(salary: 2_000_000);
    $loan = makePendingLoan($employee, amount: 1_800_000, installmentsCount: 3); // cuota: 600.000

    $result = $loan->activate(getAdminUser()->id);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('25%');
});

it('activa correctamente si la cuota es exactamente el 25% del salario', function () {
    // Salario: 2.000.000 → 25% = 500.000
    // Cuota: 500.000 → debe pasar
    $employee = makeActivateEmployee(salary: 2_000_000);
    $loan = makePendingLoan($employee, amount: 2_000_000, installmentsCount: 4); // cuota: 500.000

    $result = $loan->activate(getAdminUser()->id);

    expect($result['success'])->toBeTrue();
});

it('activa correctamente si la cuota está por debajo del 25%', function () {
    // Salario: 2.550.000 → 25% = 637.500
    // Cuota: 500.000 → debe pasar
    $employee = makeActivateEmployee(salary: 2_550_000);
    $loan = makePendingLoan($employee, amount: 2_000_000, installmentsCount: 4); // cuota: 500.000

    $result = $loan->activate(getAdminUser()->id);

    expect($result['success'])->toBeTrue();
});

it('calcula el límite del 25% para jornaleros usando daily_rate × 30', function () {
    // daily_rate: 90.000 → base mensual: 2.700.000 → 25% = 675.000
    // Cuota: 700.000 → debe fallar
    $employee = makeActivateEmployee(salaryType: 'jornal', salary: 90_000);
    $loan = makePendingLoan($employee, amount: 700_000, installmentsCount: 1);

    $result = $loan->activate(getAdminUser()->id);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('25%');
});

it('activa jornalero si la cuota está dentro del límite del 25%', function () {
    // daily_rate: 90.000 → base mensual: 2.700.000 → 25% = 675.000
    // Cuota: 600.000 → debe pasar
    $employee = makeActivateEmployee(salaryType: 'jornal', salary: 90_000);
    $loan = makePendingLoan($employee, amount: 1_200_000, installmentsCount: 2); // cuota: 600.000

    $result = $loan->activate(getAdminUser()->id);

    expect($result['success'])->toBeTrue();
});

// ─── Activación exitosa ───────────────────────────────────────────────────────

it('activa el préstamo y genera las cuotas correctamente', function () {
    $employee = makeActivateEmployee();
    $loan = makePendingLoan($employee, amount: 1_000_000, installmentsCount: 4); // cuota: 250.000

    $result = $loan->activate(getAdminUser()->id);

    expect($result['success'])->toBeTrue();

    $loan->refresh();
    expect($loan->status)->toBe('active')
        ->and($loan->granted_at)->not->toBeNull()
        ->and($loan->granted_by_id)->toBe(getAdminUser()->id)
        ->and($loan->installments()->count())->toBe(4)
        ->and($loan->installments()->where('status', 'pending')->count())->toBe(4);
});

it('la suma de cuotas generadas es igual al monto total del préstamo', function () {
    // 1.000.000 / 3 = 333.333,33 → cuota: 333.333, última absorbe diferencia
    $employee = makeActivateEmployee();
    $loan = makePendingLoan($employee, amount: 1_000_000, installmentsCount: 3);

    $loan->activate(getAdminUser()->id);

    $total = $loan->installments()->sum('amount');
    expect((float) $total)->toBe(1_000_000.0);
});

// ─── Préstamos con tasa de interés (PMT) ─────────────────────────────────────

it('calcula el installment_amount usando PMT al activar con tasa de interés', function () {
    // P=1.000.000, tasa anual=12% (r=1% mensual), n=12
    // PMT = 1.000.000 × 0,01 × (1,01)^12 / ((1,01)^12 - 1) ≈ 88.848,79
    $employee = makeActivateEmployee(salary: 2_550_000);
    $loan = makePendingLoan($employee, amount: 1_000_000, installmentsCount: 12, interestRate: 12.0);
    $expectedPmt = round(1_000_000 * 0.01 * pow(1.01, 12) / (pow(1.01, 12) - 1), 2);

    $result = $loan->activate(getAdminUser()->id);

    expect($result['success'])->toBeTrue();
    expect((float) $loan->fresh()->installment_amount)->toBe($expectedPmt);
});

it('falla si el PMT calculado con interés supera el 25% del salario', function () {
    // Salario: 800.000 → cap 25% = 200.000
    // P=3.000.000, tasa=24% anual (r=2% mensual), n=12
    // PMT ≈ 283.763 > 200.000 → debe fallar
    $employee = makeActivateEmployee(salary: 800_000);
    $loan = makePendingLoan($employee, amount: 3_000_000, installmentsCount: 12, interestRate: 24.0);

    $result = $loan->activate(getAdminUser()->id);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('25%');
});

it('activa préstamo con interés si el PMT está dentro del límite del 25%', function () {
    // Salario: 2.550.000 → cap 25% = 637.500
    // P=1.000.000, tasa=12% anual, n=12 → PMT ≈ 88.849 < 637.500 → debe pasar
    $employee = makeActivateEmployee(salary: 2_550_000);
    $loan = makePendingLoan($employee, amount: 1_000_000, installmentsCount: 12, interestRate: 12.0);

    $result = $loan->activate(getAdminUser()->id);

    expect($result['success'])->toBeTrue();
});
