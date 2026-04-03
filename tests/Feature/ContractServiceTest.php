<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\ContractService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Crea un empleado con empresa, sucursal, departamento y cargo propios.
 * Retorna un array con el employee y los IDs de position/department para pasar a makeContract.
 *
 * @return array{employee: Employee, position_id: int, department_id: int}
 */
function makeContractEmployee(): array
{
    static $ci = 4000000;
    $n = $ci++;

    $company    = Company::create(['name' => "EmpC {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch     = Branch::create(['name' => "SucC {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "DepC {$n}", 'company_id' => $company->id]);
    $position   = Position::create(['name' => "PosC {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Test',
        'last_name'  => 'Contract',
        'ci'         => (string) $n,
        'email'      => "ctr{$n}@test.com",
        'branch_id'  => $branch->id,
        'status'     => 'active',
    ]);

    return [
        'employee'      => $employee,
        'position_id'   => $position->id,
        'department_id' => $department->id,
    ];
}

/**
 * Crea un contrato para el empleado dado.
 *
 * @param  string  $type    'plazo_fijo' | 'indefinido' | 'obra'
 * @param  string  $status  'active' | 'renewed' | 'terminated'
 */
function makeContract(
    Employee $employee,
    int $positionId,
    int $departmentId,
    string $type = 'plazo_fijo',
    string $status = 'active',
    ?string $endDate = '2026-06-30',
    ?string $notes = null,
): Contract {
    return Contract::create([
        'employee_id'   => $employee->id,
        'type'          => $type,
        'start_date'    => '2026-01-01',
        'end_date'      => $endDate,
        'salary_type'   => 'mensual',
        'salary'        => 2_550_000,
        'payroll_type'  => 'monthly',
        'work_modality' => 'presencial',
        'position_id'   => $positionId,
        'department_id' => $departmentId,
        'status'        => $status,
        'notes'         => $notes,
    ]);
}

// ─── terminate() ─────────────────────────────────────────────────────────────

it('terminate establece el status del contrato en terminated', function () {
    ['employee' => $employee, 'position_id' => $posId, 'department_id' => $deptId] = makeContractEmployee();
    $contract = makeContract($employee, $posId, $deptId);

    ContractService::terminate($contract, 'Fin de proyecto');

    expect($contract->fresh()->status)->toBe('terminated');
});

it('terminate agrega el motivo a las notas cuando notes está vacío', function () {
    ['employee' => $employee, 'position_id' => $posId, 'department_id' => $deptId] = makeContractEmployee();
    $contract = makeContract($employee, $posId, $deptId, notes: null);

    ContractService::terminate($contract, 'Renuncia voluntaria');

    expect($contract->fresh()->notes)->toContain('Renuncia voluntaria');
});

it('terminate concatena el motivo a notas existentes', function () {
    ['employee' => $employee, 'position_id' => $posId, 'department_id' => $deptId] = makeContractEmployee();
    $contract = makeContract($employee, $posId, $deptId, notes: 'Nota previa.');

    ContractService::terminate($contract, 'Vencimiento del plazo');

    $notes = $contract->fresh()->notes;
    expect($notes)->toContain('Nota previa.')
        ->and($notes)->toContain('Vencimiento del plazo');
});

it('terminate usa "Sin motivo especificado" si reason es null', function () {
    ['employee' => $employee, 'position_id' => $posId, 'department_id' => $deptId] = makeContractEmployee();
    $contract = makeContract($employee, $posId, $deptId);

    ContractService::terminate($contract, null);

    expect($contract->fresh()->notes)->toContain('Sin motivo especificado');
});

// ─── renew() ─────────────────────────────────────────────────────────────────

it('renew marca el contrato original como renewed', function () {
    $user = User::factory()->create();
    Auth::login($user);

    ['employee' => $employee, 'position_id' => $posId, 'department_id' => $deptId] = makeContractEmployee();
    $contract = makeContract($employee, $posId, $deptId);

    ContractService::renew($contract, ['start_date' => '2026-07-01', 'end_date' => '2026-12-31']);

    expect($contract->fresh()->status)->toBe('renewed');
});

it('renew crea un nuevo contrato activo para el empleado', function () {
    $user = User::factory()->create();
    Auth::login($user);

    ['employee' => $employee, 'position_id' => $posId, 'department_id' => $deptId] = makeContractEmployee();
    $contract = makeContract($employee, $posId, $deptId);

    $renewed = ContractService::renew($contract, ['start_date' => '2026-07-01', 'end_date' => '2026-12-31']);

    expect($renewed->status)->toBe('active')
        ->and($renewed->employee_id)->toBe($employee->id)
        ->and($renewed->start_date->toDateString())->toBe('2026-07-01');
});

it('renew hereda salary, salary_type y payroll_type del contrato original', function () {
    $user = User::factory()->create();
    Auth::login($user);

    ['employee' => $employee, 'position_id' => $posId, 'department_id' => $deptId] = makeContractEmployee();
    $contract = makeContract($employee, $posId, $deptId);

    $renewed = ContractService::renew($contract, ['start_date' => '2026-07-01']);

    expect((float) $renewed->salary)->toBe(2_550_000.0)
        ->and($renewed->salary_type)->toBe('mensual')
        ->and($renewed->payroll_type)->toBe('monthly');
});

it('renew asigna el usuario autenticado como created_by_id', function () {
    $user = User::factory()->create();
    Auth::login($user);

    ['employee' => $employee, 'position_id' => $posId, 'department_id' => $deptId] = makeContractEmployee();
    $contract = makeContract($employee, $posId, $deptId);

    $renewed = ContractService::renew($contract, ['start_date' => '2026-07-01']);

    expect($renewed->created_by_id)->toBe($user->id);
});

it('primera renovación de plazo_fijo mantiene el tipo plazo_fijo', function () {
    $user = User::factory()->create();
    Auth::login($user);

    ['employee' => $employee, 'position_id' => $posId, 'department_id' => $deptId] = makeContractEmployee();
    $contract = makeContract($employee, $posId, $deptId, type: 'plazo_fijo');

    $renewed = ContractService::renew($contract, ['start_date' => '2026-07-01', 'end_date' => '2026-12-31']);

    expect($renewed->type)->toBe('plazo_fijo');
});

it('segunda renovación de plazo_fijo fuerza tipo indefinido (Art. 53 CLT)', function () {
    $user = User::factory()->create();
    Auth::login($user);

    ['employee' => $employee, 'position_id' => $posId, 'department_id' => $deptId] = makeContractEmployee();

    // Contrato 1: plazo_fijo original
    $contract1 = makeContract($employee, $posId, $deptId, type: 'plazo_fijo');

    // Primera renovación → sigue siendo plazo_fijo
    $contract2 = ContractService::renew($contract1, ['start_date' => '2026-07-01', 'end_date' => '2026-12-31']);
    expect($contract2->type)->toBe('plazo_fijo');

    // Segunda renovación → debe forzar indefinido
    $contract3 = ContractService::renew($contract2, ['start_date' => '2027-01-01', 'end_date' => '2027-06-30']);

    expect($contract3->type)->toBe('indefinido')
        ->and($contract3->end_date)->toBeNull();
});

it('renovación de contrato indefinido no aplica Art. 53 CLT', function () {
    $user = User::factory()->create();
    Auth::login($user);

    ['employee' => $employee, 'position_id' => $posId, 'department_id' => $deptId] = makeContractEmployee();
    $contract = makeContract($employee, $posId, $deptId, type: 'indefinido', endDate: null);

    $renewed = ContractService::renew($contract, ['start_date' => '2026-07-01']);

    expect($renewed->type)->toBe('indefinido');
});
