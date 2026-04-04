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
        'monthly_hours'                        => 240,
        'monthly_hours_nocturno'               => 210,
        'monthly_hours_mixto'                  => 225,
        'daily_hours'                          => 8,
        'daily_hours_nocturno'                 => 7,
        'daily_hours_mixto'                    => 7.5,
        'days_per_month'                       => 30,
        'overtime_multiplier_diurno'           => 1.5,
        'overtime_multiplier_nocturno'         => 2.6,
        'overtime_multiplier_holiday'          => 2.0,
        'overtime_multiplier_nocturno_holiday' => 2.6,
        'overtime_max_daily_hours'             => 3,
        'overtime_max_weekly_hours'            => 9,
        'ips_employee_rate'                    => 9,
        'ips_deduction_code'                   => 'IPS001',
        'indemnizacion_days_per_year'          => 15,
        'vacation_min_consecutive_days'        => 6,
        'vacation_min_years_service'           => 1,
        'vacation_business_days'               => [1, 2, 3, 4, 5, 6],
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
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Crea un empleado mensualizado con contrato activo.
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
 * Crea un AttendanceDay con tardanza para el empleado en la fecha dada.
 */
function makeTardinessDay(
    Employee $employee,
    string $date,
    int $lateMinutes,
    bool $approved = false,
): AttendanceDay {
    return AttendanceDay::create([
        'employee_id'                  => $employee->id,
        'date'                         => $date,
        'status'                       => 'present',
        'late_minutes'                 => $lateMinutes,
        'tardiness_deduction_approved' => $approved,
    ]);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('retorna vacío para jornaleros (trabajan por día, no se descuenta tardanza)', function () {
    $employee = makeAbsEmployee('jornal', 120_000);
    $period   = makeAbsPeriod();

    makeTardinessDay($employee, '2026-03-10', lateMinutes: 30, approved: true);

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect($result)->toMatchArray(['total' => 0.0, 'minutes' => 0, 'items' => []]);
});

it('retorna vacío si el empleado no tiene salario base válido', function () {
    $employee = makeAbsEmployee('mensual', 0);
    $period   = makeAbsPeriod();

    makeTardinessDay($employee, '2026-03-10', lateMinutes: 30, approved: true);

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect($result)->toMatchArray(['total' => 0.0, 'minutes' => 0, 'items' => []]);
});

it('retorna vacío si no hay tardanzas aprobadas en el período', function () {
    $employee = makeAbsEmployee();
    $period   = makeAbsPeriod();

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect($result)->toMatchArray(['total' => 0.0, 'minutes' => 0, 'items' => []]);
});

it('no descuenta tardanzas no aprobadas', function () {
    $employee = makeAbsEmployee();
    $period   = makeAbsPeriod();

    makeTardinessDay($employee, '2026-03-10', lateMinutes: 45, approved: false);

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect($result)->toMatchArray(['total' => 0.0, 'minutes' => 0, 'items' => []]);
});

it('calcula correctamente el descuento por 30 minutos de tardanza aprobados', function () {
    // salary=2_400_000, monthly_hours=240
    // hourlyRate = 2_400_000 / 240 = 10_000
    // 30 min = 0.5 hrs → 0.5 * 10_000 = 5_000
    $salary   = 2_400_000;
    $expected = round((30 / 60) * ($salary / 240), 2); // 5_000.0

    $employee = makeAbsEmployee('mensual', $salary);
    $period   = makeAbsPeriod();

    makeTardinessDay($employee, '2026-03-10', lateMinutes: 30, approved: true);

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe($expected)
        ->and($result['minutes'])->toBe(30)
        ->and($result['items'])->toHaveCount(1)
        ->and($result['items'][0]['description'])->toContain('30 min');
});

it('acumula tardanzas de múltiples días aprobados', function () {
    // 20 + 15 + 25 = 60 min = 1 hr → 1 * 10_000 = 10_000
    $salary   = 2_400_000;
    $expected = round((60 / 60) * ($salary / 240), 2); // 10_000.0

    $employee = makeAbsEmployee('mensual', $salary);
    $period   = makeAbsPeriod();

    makeTardinessDay($employee, '2026-03-10', lateMinutes: 20, approved: true);
    makeTardinessDay($employee, '2026-03-11', lateMinutes: 15, approved: true);
    makeTardinessDay($employee, '2026-03-12', lateMinutes: 25, approved: true);

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe($expected)
        ->and($result['minutes'])->toBe(60)
        ->and($result['items'])->toHaveCount(1);
});

it('solo descuenta días aprobados, ignora los no aprobados del mismo período', function () {
    // Solo 30 min aprobados, los otros 45 no aprobados se ignoran
    $salary   = 2_400_000;
    $expected = round((30 / 60) * ($salary / 240), 2); // 5_000.0

    $employee = makeAbsEmployee('mensual', $salary);
    $period   = makeAbsPeriod();

    makeTardinessDay($employee, '2026-03-10', lateMinutes: 30, approved: true);
    makeTardinessDay($employee, '2026-03-11', lateMinutes: 45, approved: false);

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect((float) $result['total'])->toBe($expected)
        ->and($result['minutes'])->toBe(30);
});

it('no descuenta tardanzas fuera del período', function () {
    $employee = makeAbsEmployee();
    $period   = makeAbsPeriod(month: 3); // 2026-03-01 – 2026-03-31

    makeTardinessDay($employee, '2026-02-15', lateMinutes: 30, approved: true); // mes anterior
    makeTardinessDay($employee, '2026-04-05', lateMinutes: 30, approved: true); // mes siguiente

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect($result)->toMatchArray(['total' => 0.0, 'minutes' => 0, 'items' => []]);
});

it('retorna vacío si monthly_hours es 0 en la configuración', function () {
    DB::table('settings')
        ->where('group', 'payroll')
        ->where('name', 'monthly_hours')
        ->update(['payload' => '0']);

    $employee = makeAbsEmployee();
    $period   = makeAbsPeriod();

    makeTardinessDay($employee, '2026-03-10', lateMinutes: 30, approved: true);

    $result = app(AbsencePenaltyCalculator::class)->calculate($employee, $period);

    expect($result)->toMatchArray(['total' => 0.0, 'minutes' => 0, 'items' => []]);
});
