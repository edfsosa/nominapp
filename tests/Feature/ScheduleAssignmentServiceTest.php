<?php

use App\Models\Employee;
use App\Models\EmployeeScheduleAssignment;
use App\Models\Schedule;
use App\Models\ScheduleDay;
use App\Services\ScheduleAssignmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeSchedule(string $name = 'Horario Test', string $shiftType = 'diurno'): Schedule
{
    return Schedule::create(['name' => $name, 'shift_type' => $shiftType, 'description' => null]);
}

function makeEmployee(): Employee
{
    static $ci = 1000000;
    $n = $ci++;

    return Employee::create([
        'first_name' => 'Test',
        'last_name'  => 'Employee',
        'ci'         => (string) $n,
        'email'      => "test{$n}@test.com",
        'status'     => 'active',
    ]);
}

// ─── ScheduleAssignmentService::assign() ────────────────────────────────────

it('crea una asignación nueva correctamente', function () {
    $employee = makeEmployee();
    $schedule = makeSchedule();

    $assignment = ScheduleAssignmentService::assign(
        employee:  $employee,
        schedule:  $schedule,
        validFrom: Carbon::today(),
    );

    expect($assignment)->toBeInstanceOf(EmployeeScheduleAssignment::class)
        ->and($assignment->employee_id)->toBe($employee->id)
        ->and($assignment->schedule_id)->toBe($schedule->id)
        ->and($assignment->valid_from->toDateString())->toBe(Carbon::today()->toDateString())
        ->and($assignment->valid_until)->toBeNull();
});

it('cierra la asignación abierta anterior al crear una nueva', function () {
    $employee  = makeEmployee();
    $schedule1 = makeSchedule('Horario A');
    $schedule2 = makeSchedule('Horario B');

    $first = ScheduleAssignmentService::assign(
        employee:  $employee,
        schedule:  $schedule1,
        validFrom: Carbon::today()->subDays(10),
    );

    ScheduleAssignmentService::assign(
        employee:  $employee,
        schedule:  $schedule2,
        validFrom: Carbon::today(),
    );

    $first->refresh();

    expect($first->valid_until->toDateString())
        ->toBe(Carbon::today()->subDay()->toDateString());
});

it('no cierra una asignación anterior que ya tiene valid_until', function () {
    $employee  = makeEmployee();
    $schedule1 = makeSchedule('Horario A');
    $schedule2 = makeSchedule('Horario B');

    $closed = ScheduleAssignmentService::assign(
        employee:   $employee,
        schedule:   $schedule1,
        validFrom:  Carbon::today()->subDays(20),
        validUntil: Carbon::today()->subDays(10),
    );

    ScheduleAssignmentService::assign(
        employee:  $employee,
        schedule:  $schedule2,
        validFrom: Carbon::today(),
    );

    $closed->refresh();

    // La asignación cerrada no debe haber sido modificada
    expect($closed->valid_until->toDateString())
        ->toBe(Carbon::today()->subDays(10)->toDateString());
});

it('lanza ValidationException si valid_until es anterior a valid_from', function () {
    $employee = makeEmployee();
    $schedule = makeSchedule();

    ScheduleAssignmentService::assign(
        employee:   $employee,
        schedule:   $schedule,
        validFrom:  Carbon::today(),
        validUntil: Carbon::today()->subDay(),
    );
})->throws(ValidationException::class);

it('lanza ValidationException si hay solapamiento con asignación existente', function () {
    $employee  = makeEmployee();
    $schedule1 = makeSchedule('Horario A');
    $schedule2 = makeSchedule('Horario B');

    // Asignación cerrada: del 1 al 20 del mes pasado
    ScheduleAssignmentService::assign(
        employee:   $employee,
        schedule:   $schedule1,
        validFrom:  Carbon::today()->subDays(20),
        validUntil: Carbon::today()->subDays(5),
    );

    // Intento solapar: del 10 al 15 del mes pasado
    ScheduleAssignmentService::assign(
        employee:   $employee,
        schedule:   $schedule2,
        validFrom:  Carbon::today()->subDays(15),
        validUntil: Carbon::today()->subDays(8),
    );
})->throws(ValidationException::class);

it('permite asignar un horario con fecha de inicio futura', function () {
    $employee = makeEmployee();
    $schedule = makeSchedule();

    $assignment = ScheduleAssignmentService::assign(
        employee:  $employee,
        schedule:  $schedule,
        validFrom: Carbon::today()->addWeek(),
    );

    expect($assignment->valid_from->toDateString())
        ->toBe(Carbon::today()->addWeek()->toDateString());
});

// ─── ScheduleAssignmentService::closeActive() ────────────────────────────────

it('cierra la asignación activa en la fecha indicada', function () {
    $employee = makeEmployee();
    $schedule = makeSchedule();

    $assignment = ScheduleAssignmentService::assign(
        employee:  $employee,
        schedule:  $schedule,
        validFrom: Carbon::today()->subDays(5),
    );

    ScheduleAssignmentService::closeActive($employee, Carbon::today());

    $assignment->refresh();

    expect($assignment->valid_until->toDateString())
        ->toBe(Carbon::today()->toDateString());
});

it('no modifica nada si el empleado no tiene asignación abierta', function () {
    $employee = makeEmployee();

    $updated = ScheduleAssignmentService::closeActive($employee, Carbon::today());

    expect($updated)->toBe(0);
});

// ─── Employee::getScheduleForDate() ──────────────────────────────────────────

it('retorna el horario vigente para la fecha dada', function () {
    $employee = makeEmployee();
    $schedule = makeSchedule();

    ScheduleAssignmentService::assign(
        employee:  $employee,
        schedule:  $schedule,
        validFrom: Carbon::today()->subDays(5),
    );

    expect($employee->getScheduleForDate(Carbon::today())?->id)
        ->toBe($schedule->id);
});

it('retorna null si no hay asignación ni schedule_id legacy', function () {
    $employee = makeEmployee();

    expect($employee->getScheduleForDate(Carbon::today()))->toBeNull();
});

it('cae al schedule_id legacy si no hay asignación nueva', function () {
    $schedule = makeSchedule();
    $employee = makeEmployee();
    $employee->update(['schedule_id' => $schedule->id]);

    expect($employee->getScheduleForDate(Carbon::today())?->id)
        ->toBe($schedule->id);
});

it('retorna el horario correcto para una fecha histórica', function () {
    $employee  = makeEmployee();
    $schedule1 = makeSchedule('Horario Enero');
    $schedule2 = makeSchedule('Horario Febrero');

    ScheduleAssignmentService::assign(
        employee:   $employee,
        schedule:   $schedule1,
        validFrom:  Carbon::parse('2026-01-01'),
        validUntil: Carbon::parse('2026-01-31'),
    );

    ScheduleAssignmentService::assign(
        employee:  $employee,
        schedule:  $schedule2,
        validFrom: Carbon::parse('2026-02-01'),
    );

    expect($employee->getScheduleForDate(Carbon::parse('2026-01-15'))?->id)
        ->toBe($schedule1->id)
        ->and($employee->getScheduleForDate(Carbon::parse('2026-02-10'))?->id)
        ->toBe($schedule2->id);
});

it('retorna null para una fecha anterior a cualquier asignación', function () {
    $employee = makeEmployee();
    $schedule = makeSchedule();

    ScheduleAssignmentService::assign(
        employee:  $employee,
        schedule:  $schedule,
        validFrom: Carbon::today(),
    );

    expect($employee->getScheduleForDate(Carbon::today()->subDay()))->toBeNull();
});

// ─── Schedule::isDayOff() ─────────────────────────────────────────────────────

it('isDayOff retorna false para un día activo', function () {
    $schedule = makeSchedule();

    ScheduleDay::create([
        'schedule_id' => $schedule->id,
        'day_of_week' => 1, // Lunes
        'is_active'   => true,
        'start_time'  => '08:00',
        'end_time'    => '17:00',
    ]);

    expect($schedule->isDayOff(1))->toBeFalse();
});

it('isDayOff retorna true para un día inactivo', function () {
    $schedule = makeSchedule();

    ScheduleDay::create([
        'schedule_id' => $schedule->id,
        'day_of_week' => 7, // Domingo
        'is_active'   => false,
        'start_time'  => null,
        'end_time'    => null,
    ]);

    expect($schedule->isDayOff(7))->toBeTrue();
});

it('isDayOff retorna true si el día no existe en el horario', function () {
    $schedule = makeSchedule();

    expect($schedule->isDayOff(6))->toBeTrue();
});
