<?php

use App\Models\Aguinaldo;
use App\Models\AguinaldoPeriod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\DisbursementBatch;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\Position;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeBatchEmployee(): Employee
{
    static $ci = 9000000;
    $n = $ci++;

    $company = Company::create(['name' => "EmpBatch {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch = Branch::create(['name' => "SucBatch {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "DepBatch {$n}", 'company_id' => $company->id]);
    $position = Position::create(['name' => "PosBatch {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Test',
        'last_name' => 'Batch',
        'ci' => (string) $n,
        'email' => "batch{$n}@test.com",
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
        'payment_method' => 'transfer',
    ]);

    return $employee->fresh();
}

/** Crea un préstamo aprobado listo para incluir en un lote. */
function makeApprovedLoan(Employee $employee, int $amount = 1_000_000): Loan
{
    return Loan::create([
        'employee_id' => $employee->id,
        'amount' => $amount,
        'interest_rate' => 0,
        'installments_count' => 4,
        'installment_amount' => $amount / 4,
        'status' => 'approved',
        'payment_method' => 'transfer',
        'reason' => 'personal',
    ]);
}

/** Crea un aguinaldo pendiente listo para incluir en un lote. */
function makePendingAguinaldo(Employee $employee, AguinaldoPeriod $period, float $amount = 500_000): Aguinaldo
{
    return Aguinaldo::create([
        'aguinaldo_period_id' => $period->id,
        'employee_id' => $employee->id,
        'total_earned' => $amount * 12,
        'months_worked' => 12,
        'aguinaldo_amount' => $amount,
        'status' => 'pending',
        'payment_method' => 'transfer',
        'generated_at' => now(),
    ]);
}

function makeBatchUser(): User
{
    return User::firstOrCreate(
        ['email' => 'batchadmin@test.com'],
        ['name' => 'Batch Admin', 'password' => bcrypt('password')],
    );
}

function makeLoanBatch(Company $company, array $loanIds = []): DisbursementBatch
{
    $batch = DisbursementBatch::create([
        'type' => 'loan',
        'company_id' => $company->id,
        'fecha_credito' => today()->addDays(3),
        'status' => 'pending',
        'created_by_id' => makeBatchUser()->id,
    ]);

    if ($loanIds) {
        Loan::whereIn('id', $loanIds)->update(['disbursement_batch_id' => $batch->id]);
    }

    return $batch;
}

function makeAguinaldoBatch(Company $company, array $aguinaldoIds = []): DisbursementBatch
{
    $batch = DisbursementBatch::create([
        'type' => 'aguinaldo',
        'company_id' => $company->id,
        'fecha_credito' => today()->addDays(5),
        'status' => 'pending',
        'created_by_id' => makeBatchUser()->id,
    ]);

    if ($aguinaldoIds) {
        Aguinaldo::whereIn('id', $aguinaldoIds)->update(['disbursement_batch_id' => $batch->id]);
    }

    return $batch;
}

function makeAguinaldoPeriod(Company $company, int $year = 2025): AguinaldoPeriod
{
    return AguinaldoPeriod::create([
        'company_id' => $company->id,
        'year' => $year,
        'status' => 'processing',
    ]);
}

// ─── cancel() — lotes de préstamos ───────────────────────────────────────────

it('cancel() limpia disbursement_batch_id de los préstamos incluidos', function () {
    $employee = makeBatchEmployee();
    $loan = makeApprovedLoan($employee);
    $batch = makeLoanBatch($employee->branch->company, [$loan->id]);

    $result = $batch->cancel();

    expect($result['success'])->toBeTrue();
    expect($loan->fresh()->disbursement_batch_id)->toBeNull();
    expect($batch->fresh()->status)->toBe('cancelled');
});

it('cancel() falla si el lote de préstamos no está pendiente', function () {
    $employee = makeBatchEmployee();
    $batch = makeLoanBatch($employee->branch->company);
    $batch->update(['status' => 'confirmed']);

    $result = $batch->cancel();

    expect($result['success'])->toBeFalse();
});

// ─── cancel() — lotes de aguinaldo ───────────────────────────────────────────

it('cancel() limpia disbursement_batch_id de los aguinaldos incluidos', function () {
    $employee = makeBatchEmployee();
    $period = makeAguinaldoPeriod($employee->branch->company);
    $aguinaldo = makePendingAguinaldo($employee, $period);
    $batch = makeAguinaldoBatch($employee->branch->company, [$aguinaldo->id]);

    $result = $batch->cancel();

    expect($result['success'])->toBeTrue();
    expect($aguinaldo->fresh()->disbursement_batch_id)->toBeNull();
    expect($batch->fresh()->status)->toBe('cancelled');
});

// ─── confirm() — lotes de préstamos ──────────────────────────────────────────

it('confirm() desembolsa todos los préstamos y marca el lote como confirmed', function () {
    $employee = makeBatchEmployee();
    $loan1 = makeApprovedLoan($employee, 1_000_000);
    $loan2 = makeApprovedLoan($employee, 2_000_000);
    $batch = makeLoanBatch($employee->branch->company, [$loan1->id, $loan2->id]);

    $result = $batch->confirm(
        confirmedById: makeBatchUser()->id,
        bankConfirmationPath: 'disbursement_batches/confirmations/test.pdf',
        rejectedIds: [],
    );

    expect($result['success'])->toBeTrue();
    expect($loan1->fresh()->status)->toBe('disbursed');
    expect($loan2->fresh()->status)->toBe('disbursed');
    expect($batch->fresh()->status)->toBe('confirmed');
    expect($batch->fresh()->confirmed_at)->not->toBeNull();
});

it('confirm() con rechazo parcial deja el lote como partially_confirmed', function () {
    $employee = makeBatchEmployee();
    $loan1 = makeApprovedLoan($employee);
    $loan2 = makeApprovedLoan($employee);
    $batch = makeLoanBatch($employee->branch->company, [$loan1->id, $loan2->id]);

    $batch->confirm(
        confirmedById: makeBatchUser()->id,
        bankConfirmationPath: 'confirmacion.pdf',
        rejectedIds: [$loan2->id],
        rejectionReasons: [$loan2->id => 'cuenta incorrecta'],
    );

    expect($loan1->fresh()->status)->toBe('disbursed');
    expect($loan2->fresh()->status)->toBe('approved');
    expect($loan2->fresh()->disbursement_batch_id)->toBeNull();
    expect($batch->fresh()->status)->toBe('partially_confirmed');
});

it('confirm() con todos los préstamos rechazados cancela el lote', function () {
    $employee = makeBatchEmployee();
    $loan = makeApprovedLoan($employee);
    $batch = makeLoanBatch($employee->branch->company, [$loan->id]);

    $batch->confirm(
        confirmedById: makeBatchUser()->id,
        bankConfirmationPath: 'confirmacion.pdf',
        rejectedIds: [$loan->id],
    );

    expect($loan->fresh()->status)->toBe('approved');
    expect($loan->fresh()->disbursement_batch_id)->toBeNull();
    expect($batch->fresh()->status)->toBe('cancelled');
});

it('confirm() falla si el lote de préstamos no está pendiente', function () {
    $employee = makeBatchEmployee();
    $batch = makeLoanBatch($employee->branch->company);
    $batch->update(['status' => 'confirmed']);

    $result = $batch->confirm(
        confirmedById: makeBatchUser()->id,
        bankConfirmationPath: 'confirmacion.pdf',
    );

    expect($result['success'])->toBeFalse();
});

// ─── confirm() — lotes de aguinaldo ──────────────────────────────────────────

it('confirm() marca todos los aguinaldos como pagados y el lote como confirmed', function () {
    $employee = makeBatchEmployee();
    $employee2 = makeBatchEmployee();
    $period = makeAguinaldoPeriod($employee->branch->company);
    $ag1 = makePendingAguinaldo($employee, $period, 500_000);
    $ag2 = makePendingAguinaldo($employee2, $period, 700_000);
    $batch = makeAguinaldoBatch($employee->branch->company, [$ag1->id, $ag2->id]);

    $result = $batch->confirm(
        confirmedById: makeBatchUser()->id,
        bankConfirmationPath: 'confirmacion.pdf',
        rejectedIds: [],
    );

    expect($result['success'])->toBeTrue();
    expect($ag1->fresh()->status)->toBe('paid');
    expect($ag2->fresh()->status)->toBe('paid');
    expect($batch->fresh()->status)->toBe('confirmed');
});

it('confirm() aguinaldo con rechazo parcial deja el lote como partially_confirmed', function () {
    $employee = makeBatchEmployee();
    $employee2 = makeBatchEmployee();
    $period = makeAguinaldoPeriod($employee->branch->company);
    $ag1 = makePendingAguinaldo($employee, $period);
    $ag2 = makePendingAguinaldo($employee2, $period);
    $batch = makeAguinaldoBatch($employee->branch->company, [$ag1->id, $ag2->id]);

    $batch->confirm(
        confirmedById: makeBatchUser()->id,
        bankConfirmationPath: 'confirmacion.pdf',
        rejectedIds: [$ag2->id],
    );

    expect($ag1->fresh()->status)->toBe('paid');
    expect($ag2->fresh()->status)->toBe('pending');
    expect($ag2->fresh()->disbursement_batch_id)->toBeNull();
    expect($batch->fresh()->status)->toBe('partially_confirmed');
});

it('confirm() aguinaldo con todos rechazados cancela el lote', function () {
    $employee = makeBatchEmployee();
    $period = makeAguinaldoPeriod($employee->branch->company);
    $ag = makePendingAguinaldo($employee, $period);
    $batch = makeAguinaldoBatch($employee->branch->company, [$ag->id]);

    $batch->confirm(
        confirmedById: makeBatchUser()->id,
        bankConfirmationPath: 'confirmacion.pdf',
        rejectedIds: [$ag->id],
    );

    expect($ag->fresh()->status)->toBe('pending');
    expect($ag->fresh()->disbursement_batch_id)->toBeNull();
    expect($batch->fresh()->status)->toBe('cancelled');
});

// ─── payment_method en Loan ───────────────────────────────────────────────────

it('los préstamos nuevos tienen payment_method = cash por defecto', function () {
    $employee = makeBatchEmployee();

    $loan = Loan::create([
        'employee_id' => $employee->id,
        'amount' => 500_000,
        'interest_rate' => 0,
        'installments_count' => 2,
        'installment_amount' => 250_000,
        'status' => 'pending',
        'reason' => 'personal',
    ]);

    expect($loan->fresh()->payment_method)->toBe('cash');
});

it('un préstamo con payment_method transfer puede asignarse a un lote', function () {
    $employee = makeBatchEmployee();
    $loan = makeApprovedLoan($employee);
    $batch = makeLoanBatch($employee->branch->company, [$loan->id]);

    expect($loan->fresh()->disbursement_batch_id)->toBe($batch->id);
});

// ─── relaciones en DisbursementBatch ─────────────────────────────────────────

it('la relación loans() retorna los préstamos del lote', function () {
    $employee = makeBatchEmployee();
    $loan1 = makeApprovedLoan($employee);
    $loan2 = makeApprovedLoan($employee);
    $batch = makeLoanBatch($employee->branch->company, [$loan1->id, $loan2->id]);

    expect($batch->loans()->count())->toBe(2);
});

it('la relación aguinaldos() retorna los aguinaldos del lote', function () {
    $employee = makeBatchEmployee();
    $employee2 = makeBatchEmployee();
    $period = makeAguinaldoPeriod($employee->branch->company);
    $ag1 = makePendingAguinaldo($employee, $period);
    $ag2 = makePendingAguinaldo($employee2, $period);
    $batch = makeAguinaldoBatch($employee->branch->company, [$ag1->id, $ag2->id]);

    expect($batch->aguinaldos()->count())->toBe(2);
});
