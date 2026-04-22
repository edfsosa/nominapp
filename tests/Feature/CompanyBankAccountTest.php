<?php

use App\Models\Company;
use App\Models\CompanyBankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeCompany(): Company
{
    static $n = 1;

    return Company::create([
        'name' => "Empresa Test {$n}",
        'ruc' => "{$n}-".($n % 9 + 1),
        'employer_number' => $n++,
    ]);
}

function makeCompanyAccount(Company $company, array $overrides = []): CompanyBankAccount
{
    return CompanyBankAccount::factory()->create(array_merge([
        'company_id' => $company->id,
    ], $overrides));
}

// ─── Catálogos ───────────────────────────────────────────────────────────────

it('comparte catálogos con EmployeeBankAccount', function () {
    expect(CompanyBankAccount::getBankOptions())->not->toBeEmpty();
    expect(CompanyBankAccount::getAccountTypeOptions())->toHaveKeys(['savings', 'checking', 'salary']);
    expect(CompanyBankAccount::getBankLabel('banco_itau'))->toBe('Banco Itaú Paraguay');
    expect(CompanyBankAccount::getAccountTypeLabel('checking'))->toBe('Cuenta Corriente');
    expect(CompanyBankAccount::getStatusLabel('active'))->toBe('Activa');
    expect(CompanyBankAccount::getStatusColor('inactive'))->toBe('gray');
});

// ─── Relación con Company ─────────────────────────────────────────────────────

it('una empresa puede tener múltiples cuentas bancarias', function () {
    $company = makeCompany();

    makeCompanyAccount($company);
    makeCompanyAccount($company);

    expect($company->bankAccounts()->count())->toBe(2);
});

it('primaryBankAccount retorna la cuenta principal activa', function () {
    $company = makeCompany();

    makeCompanyAccount($company, ['is_primary' => false]);
    $primary = makeCompanyAccount($company, ['is_primary' => true, 'status' => 'active']);

    expect($company->primaryBankAccount()?->id)->toBe($primary->id);
});

it('primaryBankAccount retorna null si no hay cuenta principal activa', function () {
    $company = makeCompany();

    makeCompanyAccount($company, ['is_primary' => false]);

    expect($company->primaryBankAccount())->toBeNull();
});

// ─── markAsPrimary ────────────────────────────────────────────────────────────

it('markAsPrimary marca la cuenta y desmarca las demás', function () {
    $company = makeCompany();

    $old = makeCompanyAccount($company, ['is_primary' => true]);
    $new = makeCompanyAccount($company, ['is_primary' => false]);

    $result = $new->markAsPrimary();

    expect($result['success'])->toBeTrue();
    expect($new->fresh()->is_primary)->toBeTrue();
    expect($old->fresh()->is_primary)->toBeFalse();
});

it('markAsPrimary falla si la cuenta está inactiva', function () {
    $company = makeCompany();
    $account = makeCompanyAccount($company, ['status' => 'inactive', 'is_primary' => false]);

    $result = $account->markAsPrimary();

    expect($result['success'])->toBeFalse();
    expect($account->fresh()->is_primary)->toBeFalse();
});

// ─── deactivate ───────────────────────────────────────────────────────────────

it('deactivate desactiva una cuenta no principal', function () {
    $company = makeCompany();
    $account = makeCompanyAccount($company, ['is_primary' => false]);

    $result = $account->deactivate();

    expect($result['success'])->toBeTrue();
    expect($account->fresh()->status)->toBe('inactive');
});

it('deactivate falla si la cuenta es principal y hay otras activas', function () {
    $company = makeCompany();
    $primary = makeCompanyAccount($company, ['is_primary' => true]);
    makeCompanyAccount($company, ['is_primary' => false, 'status' => 'active']);

    $result = $primary->deactivate();

    expect($result['success'])->toBeFalse();
    expect($primary->fresh()->status)->toBe('active');
});

it('deactivate permite desactivar la cuenta principal si es la única activa', function () {
    $company = makeCompany();
    $primary = makeCompanyAccount($company, ['is_primary' => true]);

    $result = $primary->deactivate();

    expect($result['success'])->toBeTrue();
    expect($primary->fresh()->status)->toBe('inactive');
    expect($primary->fresh()->is_primary)->toBeFalse();
});

it('deactivate falla si la cuenta ya está inactiva', function () {
    $company = makeCompany();
    $account = makeCompanyAccount($company, ['status' => 'inactive']);

    $result = $account->deactivate();

    expect($result['success'])->toBeFalse();
});

// ─── reactivate ───────────────────────────────────────────────────────────────

it('reactivate activa una cuenta inactiva', function () {
    $company = makeCompany();
    $account = makeCompanyAccount($company, ['status' => 'inactive']);

    $result = $account->reactivate();

    expect($result['success'])->toBeTrue();
    expect($account->fresh()->status)->toBe('active');
});

it('reactivate falla si la cuenta ya está activa', function () {
    $company = makeCompany();
    $account = makeCompanyAccount($company, ['status' => 'active']);

    $result = $account->reactivate();

    expect($result['success'])->toBeFalse();
});
