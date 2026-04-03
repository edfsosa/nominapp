<?php

use App\Models\Company;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Position;
use App\Models\Contract;
use App\Models\Schedule;
use App\Models\ScheduleDay;
use App\Models\Vacation;
use App\Models\VacationBalance;
use App\Services\ScheduleAssignmentService;
use App\Services\VacationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function seedPayrollSettings(): void
{
    $settings = [
        'vacation_min_years_service'    => 1,
        'vacation_min_consecutive_days' => 5,
        'vacation_business_days'        => [1, 2, 3, 4, 5, 6], // lunes a sábado
        'overtime_multiplier_nocturno_holiday' => 2.6,
        'min_salary_monthly'                   => 2_550_328,
        'min_salary_daily_jornal'              => 87_950,
        'family_bonus_percentage'              => 5.0,
    ];

    foreach ($settings as $name => $value) {
        DB::table('settings')->updateOrInsert(
            ['group' => 'payroll', 'name' => $name],
            ['payload' => json_encode($value)]
        );
    }
}

function makeBasicEmployee(): Employee
{
    static $ci = 5000000;
    $n = $ci++;

    return Employee::create([
        'first_name' => 'Test',
        'last_name'  => 'Vac',
        'ci'         => (string) $n,
        'email'      => "vac{$n}@test.com",
        'status'     => 'active',
    ]);
}

function makeEmployeeWithHireDate(Carbon $hireDate): Employee
{
    static $ci = 6000000;
    $n = $ci++;

    $company    = Company::create(['name' => "Empresa {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch     = Branch::create(['name' => "Sucursal {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "Depto {$n}", 'company_id' => $company->id]);
    $position   = Position::create(['name' => "Cargo {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Test',
        'last_name'  => 'Vac',
        'ci'         => (string) $n,
        'email'      => "vac{$n}@test.com",
        'branch_id'  => $branch->id,
        'status'     => 'active',
    ]);

    Contract::create([
        'employee_id'   => $employee->id,
        'type'          => 'indefinido',
        'start_date'    => $hireDate,
        'salary_type'   => 'mensual',
        'salary'        => 2_550_000,
        'position_id'   => $position->id,
        'department_id' => $department->id,
        'status'        => 'active',
    ]);

    return $employee->fresh();
}

// ─── getEntitledDays ─────────────────────────────────────────────────────────

it('retorna 0 días si el empleado tiene menos del mínimo de antigüedad', function () {
    seedPayrollSettings();

    expect(VacationService::getEntitledDays(0))->toBe(0);
});

it('retorna 12 días para 1 a 5 años de servicio', function () {
    seedPayrollSettings();

    expect(VacationService::getEntitledDays(1))->toBe(12)
        ->and(VacationService::getEntitledDays(5))->toBe(12); // tier 1: 1-5 inclusive
});

it('retorna 18 días para 6 a 10 años de servicio', function () {
    seedPayrollSettings();

    expect(VacationService::getEntitledDays(6))->toBe(18)
        ->and(VacationService::getEntitledDays(10))->toBe(18); // tier 2: 5-10, captura desde 6
});

it('retorna 30 días para 11 o más años de servicio', function () {
    seedPayrollSettings();

    expect(VacationService::getEntitledDays(11))->toBe(30)
        ->and(VacationService::getEntitledDays(20))->toBe(30);
});

// ─── getYearsOfService ───────────────────────────────────────────────────────

it('retorna 0 si el empleado no tiene contrato', function () {
    $employee = makeBasicEmployee();

    expect(VacationService::getYearsOfService($employee))->toBe(0);
});

it('calcula correctamente los años de servicio', function () {
    $hireDate = Carbon::now()->subYears(3)->subMonths(2);
    $employee = makeEmployeeWithHireDate($hireDate);

    expect(VacationService::getYearsOfService($employee))->toBe(3);
});

it('calcula años de servicio con fecha de referencia específica', function () {
    $hireDate  = Carbon::create(2020, 1, 1);
    $asOfDate  = Carbon::create(2025, 6, 1);
    $employee  = makeEmployeeWithHireDate($hireDate);

    expect(VacationService::getYearsOfService($employee, $asOfDate))->toBe(5);
});

// ─── calculateBusinessDays ───────────────────────────────────────────────────

it('cuenta correctamente los días hábiles lunes a sábado', function () {
    seedPayrollSettings();
    $employee = makeBasicEmployee();

    // Lunes 2026-03-16 a Sábado 2026-03-21 = 6 días hábiles (sin feriados)
    $start = Carbon::create(2026, 3, 16);
    $end   = Carbon::create(2026, 3, 21);

    expect(VacationService::calculateBusinessDays($employee, $start, $end))->toBe(6);
});

it('excluye domingos del conteo de días hábiles', function () {
    seedPayrollSettings();
    $employee = makeBasicEmployee();

    // Lunes 2026-03-16 a Domingo 2026-03-22 → 6 días (domingo excluido)
    $start = Carbon::create(2026, 3, 16);
    $end   = Carbon::create(2026, 3, 22);

    expect(VacationService::calculateBusinessDays($employee, $start, $end))->toBe(6);
});

it('excluye feriados del conteo de días hábiles', function () {
    seedPayrollSettings();
    $employee = makeBasicEmployee();

    // Crear un feriado en miércoles 2026-03-18
    Holiday::create(['date' => '2026-03-18', 'name' => 'Feriado Test']);

    // Lunes a sábado = 6 días, menos 1 feriado = 5
    $start = Carbon::create(2026, 3, 16);
    $end   = Carbon::create(2026, 3, 21);

    expect(VacationService::calculateBusinessDays($employee, $start, $end))->toBe(5);
});

// ─── isWorkDay ───────────────────────────────────────────────────────────────

it('considera lunes como día laboral sin horario asignado', function () {
    seedPayrollSettings();
    $employee = makeBasicEmployee();

    $monday = Carbon::create(2026, 3, 16); // lunes

    expect(VacationService::isWorkDay($employee, $monday))->toBeTrue();
});

it('considera domingo como no laboral sin horario asignado', function () {
    seedPayrollSettings();
    $employee = makeBasicEmployee();

    $sunday = Carbon::create(2026, 3, 22); // domingo

    expect(VacationService::isWorkDay($employee, $sunday))->toBeFalse();
});

it('usa el horario del empleado para determinar si es día laboral', function () {
    seedPayrollSettings();
    $employee = makeBasicEmployee();

    $schedule = Schedule::create(['name' => 'Test', 'shift_type' => 'diurno']);

    ScheduleDay::create(['schedule_id' => $schedule->id, 'day_of_week' => 1, 'is_active' => true,  'start_time' => '08:00', 'end_time' => '17:00']);
    ScheduleDay::create(['schedule_id' => $schedule->id, 'day_of_week' => 2, 'is_active' => false, 'start_time' => null,    'end_time' => null]);
    foreach (range(3, 7) as $day) {
        ScheduleDay::create(['schedule_id' => $schedule->id, 'day_of_week' => $day, 'is_active' => false, 'start_time' => null, 'end_time' => null]);
    }

    ScheduleAssignmentService::assign($employee, $schedule, Carbon::create(2026, 1, 1));
    $employee->refresh();

    $monday  = Carbon::create(2026, 3, 16);
    $tuesday = Carbon::create(2026, 3, 17);

    expect(VacationService::isWorkDay($employee, $monday))->toBeTrue()
        ->and(VacationService::isWorkDay($employee, $tuesday))->toBeFalse();
});

// ─── getOrCreateBalance ──────────────────────────────────────────────────────

it('crea un balance de vacaciones correctamente', function () {
    seedPayrollSettings();

    $hireDate = Carbon::now()->subYears(3);
    $employee = makeEmployeeWithHireDate($hireDate);

    $balance = VacationService::getOrCreateBalance($employee, 2026);

    expect($balance)->toBeInstanceOf(VacationBalance::class)
        ->and($balance->employee_id)->toBe($employee->id)
        ->and($balance->year)->toBe(2026)
        ->and($balance->entitled_days)->toBe(12) // 3 años → 12 días
        ->and($balance->used_days)->toBe(0)
        ->and($balance->pending_days)->toBe(0);
});

it('no duplica el balance si ya existe', function () {
    seedPayrollSettings();

    $employee = makeEmployeeWithHireDate(Carbon::now()->subYears(2));

    VacationService::getOrCreateBalance($employee, 2026);
    VacationService::getOrCreateBalance($employee, 2026);

    expect(VacationBalance::where('employee_id', $employee->id)->where('year', 2026)->count())->toBe(1);
});

// ─── approve / reject ────────────────────────────────────────────────────────

it('approve actualiza el estado y confirma días usados', function () {
    seedPayrollSettings();

    $employee = makeEmployeeWithHireDate(Carbon::now()->subYears(2));
    $balance  = VacationService::getOrCreateBalance($employee, 2026);
    $balance->update(['pending_days' => 5]);

    $vacation = Vacation::create([
        'employee_id'         => $employee->id,
        'vacation_balance_id' => $balance->id,
        'start_date'          => '2026-04-01',
        'end_date'            => '2026-04-07',
        'type'                => 'paid',
        'status'              => 'pending',
        'business_days'       => 5,
    ]);

    VacationService::approve($vacation);

    expect($vacation->fresh()->status)->toBe('approved')
        ->and($balance->fresh()->used_days)->toBe(5)
        ->and($balance->fresh()->pending_days)->toBe(0);
});

it('reject actualiza el estado y libera los días pendientes', function () {
    seedPayrollSettings();

    $employee = makeEmployeeWithHireDate(Carbon::now()->subYears(2));
    $balance  = VacationService::getOrCreateBalance($employee, 2026);
    $balance->update(['pending_days' => 5]);

    $vacation = Vacation::create([
        'employee_id'         => $employee->id,
        'vacation_balance_id' => $balance->id,
        'start_date'          => '2026-04-01',
        'end_date'            => '2026-04-07',
        'type'                => 'paid',
        'status'              => 'pending',
        'business_days'       => 5,
    ]);

    VacationService::reject($vacation);

    expect($vacation->fresh()->status)->toBe('rejected')
        ->and($balance->fresh()->pending_days)->toBe(0);
});

// ─── validateRequest ─────────────────────────────────────────────────────────

it('falla si el empleado no tiene la antigüedad mínima', function () {
    seedPayrollSettings();

    $employee = makeEmployeeWithHireDate(Carbon::now()->subMonths(6)); // 0 años

    $result = VacationService::validateRequest(
        $employee,
        Carbon::create(2026, 4, 1),
        Carbon::create(2026, 4, 10),
    );

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

it('falla si el período no contiene días hábiles', function () {
    seedPayrollSettings();

    $employee = makeEmployeeWithHireDate(Carbon::now()->subYears(2));

    // Solo domingo
    $result = VacationService::validateRequest(
        $employee,
        Carbon::create(2026, 3, 22),
        Carbon::create(2026, 3, 22),
    );

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toContain('El período seleccionado no contiene días hábiles.');
});

it('falla si no hay suficiente saldo disponible', function () {
    seedPayrollSettings();

    $employee = makeEmployeeWithHireDate(Carbon::now()->subYears(2));
    $balance  = VacationService::getOrCreateBalance($employee, 2026);
    $balance->update(['used_days' => 12]); // agota los 12 días

    $result = VacationService::validateRequest(
        $employee,
        Carbon::create(2026, 4, 1),
        Carbon::create(2026, 4, 10),
    );

    expect($result['valid'])->toBeFalse()
        ->and(collect($result['errors'])->contains(fn($e) => str_contains($e, 'suficientes días')))->toBeTrue();
});

it('agrega advertencia si los días son menores al mínimo consecutivo', function () {
    seedPayrollSettings();

    $employee = makeEmployeeWithHireDate(Carbon::now()->subYears(2));

    // 2 días hábiles (lunes y martes), menos del mínimo de 5
    $result = VacationService::validateRequest(
        $employee,
        Carbon::create(2026, 3, 16),
        Carbon::create(2026, 3, 17),
    );

    expect($result['warnings'])->not->toBeEmpty();
});

it('retorna válido con un período correcto y saldo suficiente', function () {
    seedPayrollSettings();

    $employee = makeEmployeeWithHireDate(Carbon::now()->subYears(2));
    VacationService::getOrCreateBalance($employee, 2026);

    // Lunes a viernes (5 días hábiles, igual al mínimo)
    $result = VacationService::validateRequest(
        $employee,
        Carbon::create(2026, 4, 6),
        Carbon::create(2026, 4, 10),
    );

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty()
        ->and($result['business_days'])->toBe(5);
});
