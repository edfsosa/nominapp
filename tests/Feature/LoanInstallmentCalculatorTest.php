<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Services\LoanInstallmentCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Crea un empleado con contrato activo para tests de LoanInstallmentCalculator.
 */
function makeLoanEmployee(): Employee
{
    static $ci = 6000000;
    $n = $ci++;

    $company    = Company::create(['name' => "EmpL {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch     = Branch::create(['name' => "SucL {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "DepL {$n}", 'company_id' => $company->id]);
    $position   = Position::create(['name' => "PosL {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Test',
        'last_name'  => 'Loan',
        'ci'         => (string) $n,
        'email'      => "loan{$n}@test.com",
        'branch_id'  => $branch->id,
        'status'     => 'active',
    ]);

    Contract::create([
        'employee_id'   => $employee->id,
        'type'          => 'indefinido',
        'start_date'    => Carbon::now()->subYear(),
        'salary_type'   => 'mensual',
        'salary'        => 2_550_000,
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
function makeLoanPeriod(int $month = 3): PayrollPeriod
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
 * Crea un Loan activo para el empleado.
 *
 * @param  string  $type    'loan' | 'advance'
 * @param  string  $status  'active' | 'paid' | 'cancelled' | 'defaulted'
 */
function makeLoan(
    Employee $employee,
    string $type = 'loan',
    int $installmentsCount = 6,
    int $installmentAmount = 500_000,
    string $status = 'active',
): Loan {
    return Loan::create([
        'employee_id'       => $employee->id,
        'type'              => $type,
        'amount'            => $installmentsCount * $installmentAmount,
        'installments_count' => $installmentsCount,
        'installment_amount' => $installmentAmount,
        'status'            => $status,
        'granted_at'        => Carbon::now()->subMonth(),
    ]);
}

/**
 * Crea una LoanInstallment para el préstamo dado.
 */
function makeLoanInstallment(
    Loan $loan,
    int $number,
    string $dueDate,
    string $status = 'pending',
    int $amount = 500_000,
): LoanInstallment {
    return LoanInstallment::create([
        'loan_id'            => $loan->id,
        'installment_number' => $number,
        'amount'             => $amount,
        'due_date'           => $dueDate,
        'status'             => $status,
    ]);
}

// ─── calculate() ─────────────────────────────────────────────────────────────

it('retorna total 0 e items vacíos si el empleado no tiene préstamos activos', function () {
    $employee = makeLoanEmployee();
    $period   = makeLoanPeriod();

    $result = app(LoanInstallmentCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe(0)
        ->and($result['items'])->toBeEmpty()
        ->and($result['installments'])->toBeEmpty();
});

it('incluye cuota pendiente con due_date dentro del período', function () {
    $employee = makeLoanEmployee();
    $period   = makeLoanPeriod(month: 3);
    $loan     = makeLoan($employee);
    $inst     = makeLoanInstallment($loan, 1, '2026-03-15');

    $result = app(LoanInstallmentCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe(500_000.0)
        ->and($result['items'])->toHaveCount(1)
        ->and($result['installments'])->toHaveCount(1);
});

it('usa descripción "Préstamo" para cuotas de tipo loan', function () {
    $employee = makeLoanEmployee();
    $period   = makeLoanPeriod();
    $loan     = makeLoan($employee, type: 'loan', installmentsCount: 3);
    makeLoanInstallment($loan, 1, '2026-03-15');

    $result = app(LoanInstallmentCalculator::class)->calculate($employee, $period);

    expect($result['items'][0]['description'])->toContain('Préstamo')
        ->and($result['items'][0]['description'])->toContain('1/3');
});

it('usa descripción "Adelanto" para cuotas de tipo advance', function () {
    $employee = makeLoanEmployee();
    $period   = makeLoanPeriod();
    $loan     = makeLoan($employee, type: 'advance', installmentsCount: 1);
    makeLoanInstallment($loan, 1, '2026-03-31');

    $result = app(LoanInstallmentCalculator::class)->calculate($employee, $period);

    expect($result['items'][0]['description'])->toContain('Adelanto');
});

it('excluye cuotas ya pagadas', function () {
    $employee = makeLoanEmployee();
    $period   = makeLoanPeriod();
    $loan     = makeLoan($employee);
    makeLoanInstallment($loan, 1, '2026-03-15', status: 'paid');

    $result = app(LoanInstallmentCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe(0)
        ->and($result['items'])->toBeEmpty();
});

it('excluye cuotas con due_date fuera del período', function () {
    $employee = makeLoanEmployee();
    $period   = makeLoanPeriod(month: 3); // 2026-03-01 – 2026-03-31
    $loan     = makeLoan($employee);

    makeLoanInstallment($loan, 1, '2026-02-28'); // antes del período
    makeLoanInstallment($loan, 2, '2026-04-01'); // después del período

    $result = app(LoanInstallmentCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe(0)
        ->and($result['items'])->toBeEmpty();
});

it('excluye cuotas de préstamos inactivos (cancelled, paid)', function () {
    $employee  = makeLoanEmployee();
    $period    = makeLoanPeriod();
    $cancelled = makeLoan($employee, status: 'cancelled');
    $paid      = makeLoan($employee, status: 'paid');

    makeLoanInstallment($cancelled, 1, '2026-03-15');
    makeLoanInstallment($paid, 1, '2026-03-15');

    $result = app(LoanInstallmentCalculator::class)->calculate($employee, $period);

    expect($result['total'])->toBe(0)
        ->and($result['items'])->toBeEmpty();
});

it('excluye cuotas de préstamos de otro empleado', function () {
    $employee1 = makeLoanEmployee();
    $employee2 = makeLoanEmployee();
    $period    = makeLoanPeriod();
    $loan      = makeLoan($employee2);
    makeLoanInstallment($loan, 1, '2026-03-15');

    $result = app(LoanInstallmentCalculator::class)->calculate($employee1, $period);

    expect($result['total'])->toBe(0)
        ->and($result['items'])->toBeEmpty();
});

it('acumula correctamente múltiples cuotas en el período', function () {
    $employee = makeLoanEmployee();
    $period   = makeLoanPeriod();

    $loan1 = makeLoan($employee, installmentAmount: 300_000);
    $loan2 = makeLoan($employee, installmentAmount: 200_000);

    makeLoanInstallment($loan1, 1, '2026-03-10', amount: 300_000);
    makeLoanInstallment($loan2, 1, '2026-03-20', amount: 200_000);

    $result = app(LoanInstallmentCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe(500_000.0)
        ->and($result['items'])->toHaveCount(2)
        ->and($result['installments'])->toHaveCount(2);
});

// ─── markInstallmentsAsPaid() ────────────────────────────────────────────────

it('retorna 0 si la lista de IDs está vacía', function () {
    $count = app(LoanInstallmentCalculator::class)->markInstallmentsAsPaid([]);

    expect($count)->toBe(0);
});

it('marca cuotas como pagadas y retorna el número de cuotas procesadas', function () {
    $employee = makeLoanEmployee();
    $loan     = makeLoan($employee);
    $inst1    = makeLoanInstallment($loan, 1, '2026-03-15');
    $inst2    = makeLoanInstallment($loan, 2, '2026-04-15');

    $count = app(LoanInstallmentCalculator::class)->markInstallmentsAsPaid([$inst1->id, $inst2->id]);

    expect($count)->toBe(2)
        ->and($inst1->fresh()->status)->toBe('paid')
        ->and($inst2->fresh()->status)->toBe('paid')
        ->and($inst1->fresh()->paid_at)->not->toBeNull();
});

it('ignora cuotas que ya están pagadas al marcar', function () {
    $employee = makeLoanEmployee();
    $loan     = makeLoan($employee);
    $pending  = makeLoanInstallment($loan, 1, '2026-03-15', status: 'pending');
    $alreadyPaid = makeLoanInstallment($loan, 2, '2026-04-15', status: 'paid');

    $count = app(LoanInstallmentCalculator::class)->markInstallmentsAsPaid([$pending->id, $alreadyPaid->id]);

    // Solo cuenta la cuota que estaba pendiente
    expect($count)->toBe(1);
});

it('cierra el préstamo cuando todas sus cuotas quedan pagadas', function () {
    $employee = makeLoanEmployee();
    $loan     = makeLoan($employee, installmentsCount: 2);
    $inst1    = makeLoanInstallment($loan, 1, '2026-02-15', status: 'paid');
    $inst2    = makeLoanInstallment($loan, 2, '2026-03-15', status: 'pending');

    app(LoanInstallmentCalculator::class)->markInstallmentsAsPaid([$inst2->id]);

    expect($loan->fresh()->status)->toBe('paid');
});

it('no cierra el préstamo si aún quedan cuotas pendientes', function () {
    $employee = makeLoanEmployee();
    $loan     = makeLoan($employee, installmentsCount: 3);
    $inst1    = makeLoanInstallment($loan, 1, '2026-03-15');
    makeLoanInstallment($loan, 2, '2026-04-15');
    makeLoanInstallment($loan, 3, '2026-05-15');

    app(LoanInstallmentCalculator::class)->markInstallmentsAsPaid([$inst1->id]);

    expect($loan->fresh()->status)->toBe('active');
});
