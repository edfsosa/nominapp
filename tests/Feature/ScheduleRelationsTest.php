<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeScheduleAssignment;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\ScheduleDay;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeSchedEmployee(): Employee
{
    static $ci = 4000000;
    $n = $ci++;

    $company    = Company::create(['name' => "Empresa {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch     = Branch::create(['name' => "Sucursal {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "Depto {$n}", 'company_id' => $company->id]);
    $position   = Position::create(['name' => "Cargo {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Test',
        'last_name'  => 'Sched',
        'ci'         => (string) $n,
        'email'      => "sched{$n}@test.com",
        'branch_id'  => $branch->id,
        'status'     => 'active',
    ]);

    Contract::create([
        'employee_id'   => $employee->id,
        'type'          => 'indefinido',
        'start_date'    => Carbon::now()->subYear(),
        'salary_type'   => 'mensual',
        'salary'        => 2_550_000,
        'position_id'   => $position->id,
        'department_id' => $department->id,
        'status'        => 'active',
    ]);

    return $employee->fresh();
}

function makeSchedSchedule(): Schedule
{
    return Schedule::create(['name' => 'Horario Test', 'shift_type' => 'diurno']);
}

function addDay(Schedule $schedule, int $dayOfWeek, bool $isActive = true): ScheduleDay
{
    return ScheduleDay::create([
        'schedule_id' => $schedule->id,
        'day_of_week' => $dayOfWeek,
        'is_active'   => $isActive,
        'start_time'  => $isActive ? '08:00' : null,
        'end_time'    => $isActive ? '17:00' : null,
    ]);
}

function assignEmployee(Employee $employee, Schedule $schedule, string $validFrom = null, string $validUntil = null): EmployeeScheduleAssignment
{
    return EmployeeScheduleAssignment::create([
        'employee_id' => $employee->id,
        'schedule_id' => $schedule->id,
        'valid_from'  => $validFrom ?? Carbon::today()->toDateString(),
        'valid_until' => $validUntil,
    ]);
}

// ─── activeDays() ─────────────────────────────────────────────────────────────

it('activeDays retorna solo los días con is_active = true', function () {
    $schedule = makeSchedSchedule();

    addDay($schedule, 1, true);  // lunes activo
    addDay($schedule, 2, false); // martes inactivo
    addDay($schedule, 3, true);  // miércoles activo

    $active = $schedule->activeDays()->get();

    expect($active)->toHaveCount(2)
        ->and($active->pluck('day_of_week')->toArray())->toEqual([1, 3]);
});

it('activeDays retorna vacío si no hay días activos', function () {
    $schedule = makeSchedSchedule();

    addDay($schedule, 1, false);
    addDay($schedule, 7, false);

    expect($schedule->activeDays()->count())->toBe(0);
});

it('activeDays retorna los días ordenados por day_of_week', function () {
    $schedule = makeSchedSchedule();

    // Insertar en orden inverso
    addDay($schedule, 5, true);
    addDay($schedule, 2, true);
    addDay($schedule, 1, true);

    $days = $schedule->activeDays()->get();

    expect($days->pluck('day_of_week')->toArray())->toEqual([1, 2, 5]);
});

it('activeDays no incluye días de otro horario', function () {
    $scheduleA = makeSchedSchedule();
    $scheduleB = makeSchedSchedule();

    addDay($scheduleA, 1, true);
    addDay($scheduleB, 2, true);
    addDay($scheduleB, 3, true);

    expect($scheduleA->activeDays()->count())->toBe(1);
});

// ─── currentEmployees() ───────────────────────────────────────────────────────

it('currentEmployees retorna empleados con asignación vigente sin fecha de fin', function () {
    $schedule = makeSchedSchedule();
    $employee = makeSchedEmployee();

    assignEmployee($employee, $schedule, Carbon::today()->toDateString(), null);

    expect($schedule->currentEmployees()->count())->toBe(1)
        ->and($schedule->currentEmployees()->first()->id)->toBe($employee->id);
});

it('currentEmployees retorna empleados cuya asignación aún no vence', function () {
    $schedule = makeSchedSchedule();
    $employee = makeSchedEmployee();

    assignEmployee($employee, $schedule, Carbon::today()->toDateString(), Carbon::today()->addMonths(3)->toDateString());

    expect($schedule->currentEmployees()->count())->toBe(1);
});

it('currentEmployees excluye empleados cuya asignación aún no comenzó', function () {
    $schedule = makeSchedSchedule();
    $employee = makeSchedEmployee();

    assignEmployee($employee, $schedule, Carbon::tomorrow()->toDateString(), null);

    expect($schedule->currentEmployees()->count())->toBe(0);
});

it('currentEmployees excluye empleados cuya asignación ya venció', function () {
    $schedule = makeSchedSchedule();
    $employee = makeSchedEmployee();

    assignEmployee($employee, $schedule, Carbon::today()->subMonths(3)->toDateString(), Carbon::yesterday()->toDateString());

    expect($schedule->currentEmployees()->count())->toBe(0);
});

it('currentEmployees retorna múltiples empleados activos', function () {
    $schedule  = makeSchedSchedule();
    $employee1 = makeSchedEmployee();
    $employee2 = makeSchedEmployee();
    $employee3 = makeSchedEmployee();

    assignEmployee($employee1, $schedule);
    assignEmployee($employee2, $schedule);
    assignEmployee($employee3, $schedule);

    expect($schedule->currentEmployees()->count())->toBe(3);
});

it('currentEmployees no incluye empleados de otro horario', function () {
    $scheduleA = makeSchedSchedule();
    $scheduleB = makeSchedSchedule();
    $employee1 = makeSchedEmployee();
    $employee2 = makeSchedEmployee();

    assignEmployee($employee1, $scheduleA);
    assignEmployee($employee2, $scheduleB);

    expect($scheduleA->currentEmployees()->count())->toBe(1)
        ->and($scheduleA->currentEmployees()->first()->id)->toBe($employee1->id);
});

it('currentEmployees ignora asignaciones anteriores del mismo empleado', function () {
    $schedule = makeSchedSchedule();
    $employee = makeSchedEmployee();

    // Asignación vencida
    assignEmployee($employee, $schedule, Carbon::today()->subYear()->toDateString(), Carbon::today()->subMonths(1)->toDateString());
    // Asignación vigente
    assignEmployee($employee, $schedule, Carbon::today()->toDateString(), null);

    $employees = $schedule->currentEmployees()->get();

    expect($employees->pluck('id')->contains($employee->id))->toBeTrue();
});
