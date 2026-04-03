<?php

use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Services\AbsencePenaltyCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    $settings = [
        'monthly_hours'                => 240,
        'monthly_hours_nocturno'       => 210,
        'monthly_hours_mixto'          => 225,
        'daily_hours'                  => 8,
        'daily_hours_nocturno'         => 7,
        'daily_hours_mixto'            => 7.5,
        'days_per_month'               => 30,
        'overtime_multiplier_diurno'           => 1.5,
        'overtime_multiplier_nocturno'         => 2.6,
        'overtime_multiplier_holiday'          => 2.0,
        'overtime_multiplier_nocturno_holiday' => 2.6,
        'overtime_max_daily_hours'     => 3,
        'ips_employee_rate'            => 9,
        'ips_deduction_code'           => 'IPS001',
        'indemnizacion_days_per_year'  => 15,
        'vacation_min_consecutive_days' => 6,
        'vacation_min_years_service'   => 1,
        'vacation_business_days'       => [1, 2, 3, 4, 5, 6],
        'min_salary_monthly'           => 2_550_328,
        'min_salary_daily_jornal'      => 87_950,
        'family_bonus_percentage'      => 5.0,
    ];

    foreach ($settings as $name => $value) {
        DB::table('settings')->updateOrInsert(
            ['group' => 'payroll', 'name' => $name],
            ['payload' => json_encode($value)]
        );
    }
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Crea un empleado con contrato activo para tests de AbsencePenaltyCalculator.
 *
 * @param  string  $salaryType  'mensual' | 'jornal'
 * @param  int     $salary      Monto del salario
 */
function makeAbsEmployee(string $salaryType = 'mensual', int $salary = 2_400_000): Employee
{
    static $ci = 9000000;
    $n = $ci++;

    $company    = Company::create(['name' => "EmpA {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch     = Branch::create(['name' => "SucA {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "DepA {$n}", 'company_id' => $company->id]);
    $position   = Position::create(['name' => "PosA {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Test',
        'last_name'  => 'Abs',
        'ci'         => (string) $n,
        'email'      => "abs{$n}@test.com",
        'branch_id'  => $branch->id,
        'status'     => 'active',
    ]);

    Contract::create([
        'employee_id'   => $employee->id,
        'type'          => 'indefinido',
        'start_date'    => Carbon::now()->subYear(),
        'salary_type'   => $salaryType,
        'salary'        => $salary,
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
function makeAbsPeriod(int $month = 3): PayrollPeriod
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
 * Crea un registro de AttendanceDay para el empleado en la fecha dada.
 */
function makeAbsenceDay(
    Employee $employee,
    string $date,
    string $status = 'absent',
    bool $isHoliday = false,
    bool $isWeekend = false,
): AttendanceDay {
    return AttendanceDay::create([
        'employee_id' => $employee->id,
        'date'        => $date,
        'status'      => $status,
        'is_holiday'  => $isHoliday,
        'is_weekend'  => $isWeekend,
    ]);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('retorna vacío para jornaleros (su penalización ya está implícita en días trabajados)', function () {
    $employee = makeAbsEmployee('jornal', 120_000);
    $period   = makeAbsPeriod();

    makeAbsenceDay($employee, '2026-03-10');

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect($result)->toMatchArray(['total' => 0, 'days' => 0, 'items' => []]);
});

it('retorna vacío si el empleado no tiene salario base válido', function () {
    // salary=0 → base_salary attribute devuelve 0.0
    $employee = makeAbsEmployee('mensual', 0);
    $period   = makeAbsPeriod();

    makeAbsenceDay($employee, '2026-03-10');

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect($result)->toMatchArray(['total' => 0, 'days' => 0, 'items' => []]);
});

it('retorna total 0 si no hay días de ausencia en el período', function () {
    $employee = makeAbsEmployee();
    $period   = makeAbsPeriod();

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect($result)->toMatchArray(['total' => 0, 'days' => 0, 'items' => []]);
});

it('calcula correctamente la penalización por un día de ausencia', function () {
    // salary=2_400_000, monthly_hours=240, daily_hours=8
    // hourlyRate = 2_400_000 / 240 = 10_000
    // dailyRate  = 10_000 * 8   = 80_000
    $salary   = 2_400_000;
    $expected = round(($salary / 240) * 8, 2); // 80_000.0

    $employee = makeAbsEmployee('mensual', $salary);
    $period   = makeAbsPeriod();

    makeAbsenceDay($employee, '2026-03-10');

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe($expected)
        ->and($result['days'])->toBe(1)
        ->and($result['items'])->toHaveCount(1)
        ->and($result['items'][0]['description'])->toContain('1 día');
});

it('acumula correctamente múltiples días de ausencia', function () {
    $salary   = 2_400_000;
    $daily    = round(($salary / 240) * 8, 2); // 80_000.0
    $expected = round($daily * 3, 2);           // 240_000.0

    $employee = makeAbsEmployee('mensual', $salary);
    $period   = makeAbsPeriod();

    makeAbsenceDay($employee, '2026-03-10');
    makeAbsenceDay($employee, '2026-03-11');
    makeAbsenceDay($employee, '2026-03-12');

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe($expected)
        ->and($result['days'])->toBe(3)
        ->and($result['items'][0]['description'])->toContain('3 día');
});

it('no penaliza ausencias en días feriados', function () {
    $employee = makeAbsEmployee();
    $period   = makeAbsPeriod();

    makeAbsenceDay($employee, '2026-03-01', isHoliday: true);

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect($result)->toMatchArray(['total' => 0, 'days' => 0, 'items' => []]);
});

it('no penaliza ausencias en fines de semana', function () {
    $employee = makeAbsEmployee();
    $period   = makeAbsPeriod();

    makeAbsenceDay($employee, '2026-03-07', isWeekend: true); // sábado

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect($result)->toMatchArray(['total' => 0, 'days' => 0, 'items' => []]);
});

it('no cuenta días con status distinto de absent', function () {
    $employee = makeAbsEmployee();
    $period   = makeAbsPeriod();

    makeAbsenceDay($employee, '2026-03-10', status: 'present');
    makeAbsenceDay($employee, '2026-03-11', status: 'on_leave');

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect($result)->toMatchArray(['total' => 0, 'days' => 0, 'items' => []]);
});

it('no penaliza días de ausencia fuera del período', function () {
    $employee = makeAbsEmployee();
    $period   = makeAbsPeriod(month: 3); // 2026-03-01 – 2026-03-31

    makeAbsenceDay($employee, '2026-02-15'); // mes anterior
    makeAbsenceDay($employee, '2026-04-05'); // mes siguiente

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect($result)->toMatchArray(['total' => 0, 'days' => 0, 'items' => []]);
});

it('penaliza solo las ausencias dentro del período, ignorando las externas', function () {
    $salary   = 2_400_000;
    $expected = round(($salary / 240) * 8, 2); // 80_000.0 — solo 1 día dentro del período

    $employee = makeAbsEmployee('mensual', $salary);
    $period   = makeAbsPeriod(month: 3);

    makeAbsenceDay($employee, '2026-02-28'); // fuera
    makeAbsenceDay($employee, '2026-03-15'); // dentro
    makeAbsenceDay($employee, '2026-04-01'); // fuera

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe($expected)
        ->and($result['days'])->toBe(1);
});

it('retorna vacío si monthly_hours es 0 en la configuración', function () {
    DB::table('settings')
        ->where('group', 'payroll')
        ->where('name', 'monthly_hours')
        ->update(['payload' => '0']);

    $employee = makeAbsEmployee();
    $period   = makeAbsPeriod();

    makeAbsenceDay($employee, '2026-03-10');

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect($result)->toMatchArray(['total' => 0, 'days' => 0, 'items' => []]);
});
