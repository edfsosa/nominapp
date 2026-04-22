<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeBankEmployee(): Employee
{
    static $ci = 9000000;
    $n = $ci++;

    $company = Company::create(['name' => "EmpBank {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch = Branch::create(['name' => "SucBank {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "DepBank {$n}", 'company_id' => $company->id]);
    $position = Position::create(['name' => "PosBank {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Test',
        'last_name' => 'BankUser',
        'ci' => (string) $n,
        'branch_id' => $branch->id,
        'status' => 'active',
    ]);

    Contract::create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
        'department_id' => $department->id,
        'type' => 'indefinido',
        'salary_type' => 'mensual',
        'salary' => 3_000_000,
        'payroll_type' => 'monthly',
        'payment_method' => 'debit',
        'start_date' => now()->subYear(),
        'status' => 'active',
    ]);

    return $employee->fresh();
}

function makeBankAccount(Employee $employee, array $overrides = []): EmployeeBankAccount
{
    return EmployeeBankAccount::factory()->create(array_merge([
        'employee_id' => $employee->id,
    ], $overrides));
}

// ─── Catálogos ───────────────────────────────────────────────────────────────

it('tiene bancos y tipos de cuenta definidos', function () {
    expect(EmployeeBankAccount::getBankOptions())->not->toBeEmpty();
    expect(EmployeeBankAccount::getAccountTypeOptions())->toHaveKeys(['savings', 'checking', 'salary']);
});

it('retorna labels correctos para banco y tipo de cuenta', function () {
    expect(EmployeeBankAccount::getBankLabel('banco_itau'))->toBe('Banco Itaú Paraguay');
    expect(EmployeeBankAccount::getAccountTypeLabel('savings'))->toBe('Caja de Ahorro');
    expect(EmployeeBankAccount::getStatusLabel('active'))->toBe('Activa');
    expect(EmployeeBankAccount::getStatusColor('active'))->toBe('success');
    expect(EmployeeBankAccount::getStatusColor('inactive'))->toBe('gray');
});

// ─── Relación con Employee ────────────────────────────────────────────────────

it('un empleado puede tener múltiples cuentas bancarias', function () {
    $employee = makeBankEmployee();

    makeBankAccount($employee);
    makeBankAccount($employee);

    expect($employee->bankAccounts()->count())->toBe(2);
});

it('primaryBankAccount retorna la cuenta principal activa', function () {
    $employee = makeBankEmployee();

    makeBankAccount($employee, ['is_primary' => false]);
    $primary = makeBankAccount($employee, ['is_primary' => true, 'status' => 'active']);

    expect($employee->primaryBankAccount()?->id)->toBe($primary->id);
});

it('primaryBankAccount retorna null si no hay cuenta principal activa', function () {
    $employee = makeBankEmployee();

    makeBankAccount($employee, ['is_primary' => false]);

    expect($employee->primaryBankAccount())->toBeNull();
});

// ─── markAsPrimary ────────────────────────────────────────────────────────────

it('markAsPrimary marca la cuenta y desmarca las demás', function () {
    $employee = makeBankEmployee();

    $old = makeBankAccount($employee, ['is_primary' => true]);
    $new = makeBankAccount($employee, ['is_primary' => false]);

    $result = $new->markAsPrimary();

    expect($result['success'])->toBeTrue();
    expect($new->fresh()->is_primary)->toBeTrue();
    expect($old->fresh()->is_primary)->toBeFalse();
});

it('markAsPrimary falla si la cuenta está inactiva', function () {
    $employee = makeBankEmployee();
    $account = makeBankAccount($employee, ['status' => 'inactive', 'is_primary' => false]);

    $result = $account->markAsPrimary();

    expect($result['success'])->toBeFalse();
    expect($account->fresh()->is_primary)->toBeFalse();
});

// ─── deactivate ───────────────────────────────────────────────────────────────

it('deactivate desactiva una cuenta no principal', function () {
    $employee = makeBankEmployee();
    $account = makeBankAccount($employee, ['is_primary' => false]);

    $result = $account->deactivate();

    expect($result['success'])->toBeTrue();
    expect($account->fresh()->status)->toBe('inactive');
});

it('deactivate falla si la cuenta es principal y hay otras activas', function () {
    $employee = makeBankEmployee();
    $primary = makeBankAccount($employee, ['is_primary' => true]);
    makeBankAccount($employee, ['is_primary' => false, 'status' => 'active']);

    $result = $primary->deactivate();

    expect($result['success'])->toBeFalse();
    expect($primary->fresh()->status)->toBe('active');
});

it('deactivate permite desactivar la cuenta principal si es la única activa', function () {
    $employee = makeBankEmployee();
    $primary = makeBankAccount($employee, ['is_primary' => true]);

    $result = $primary->deactivate();

    expect($result['success'])->toBeTrue();
    expect($primary->fresh()->status)->toBe('inactive');
    expect($primary->fresh()->is_primary)->toBeFalse();
});

it('deactivate falla si la cuenta ya está inactiva', function () {
    $employee = makeBankEmployee();
    $account = makeBankAccount($employee, ['status' => 'inactive']);

    $result = $account->deactivate();

    expect($result['success'])->toBeFalse();
});

// ─── reactivate ───────────────────────────────────────────────────────────────

it('reactivate activa una cuenta inactiva', function () {
    $employee = makeBankEmployee();
    $account = makeBankAccount($employee, ['status' => 'inactive']);

    $result = $account->reactivate();

    expect($result['success'])->toBeTrue();
    expect($account->fresh()->status)->toBe('active');
});

it('reactivate falla si la cuenta ya está activa', function () {
    $employee = makeBankEmployee();
    $account = makeBankAccount($employee, ['status' => 'active']);

    $result = $account->reactivate();

    expect($result['success'])->toBeFalse();
});

// ─── Relación con Payroll ─────────────────────────────────────────────────────

it('una cuenta bancaria puede estar asociada a nóminas', function () {
    $employee = makeBankEmployee();
    $account = makeBankAccount($employee, ['is_primary' => true]);

    $period = PayrollPeriod::create([
        'name' => 'Abril 2026',
        'frequency' => 'monthly',
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-30',
        'status' => 'draft',
    ]);

    Payroll::create([
        'employee_id' => $employee->id,
        'payroll_period_id' => $period->id,
        'bank_account_id' => $account->id,
        'base_salary' => 3_000_000,
        'gross_salary' => 3_000_000,
        'total_deductions' => 0,
        'total_perceptions' => 0,
        'ips_perceptions' => 0,
        'net_salary' => 3_000_000,
        'status' => 'draft',
        'generated_at' => now(),
    ]);

    expect($account->payrolls()->count())->toBe(1);
    expect($employee->payrolls()->first()->bankAccount->id)->toBe($account->id);
});
