<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Vacation;
use App\Models\VacationBalance;
use App\Settings\PayrollSettings;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class VacationService
{
    /**
     * Días de vacaciones según antigüedad (Ley Paraguaya)
     * Configurado en config/payroll.php -> vacation.tiers
     */
    public static function getEntitledDays(int $yearsOfService): int
    {
        $minYearsService = app(PayrollSettings::class)->vacation_min_years_service;

        if ($yearsOfService < $minYearsService) {
            return 0;
        }

        $tiers = config('payroll.vacation.tiers', [
            ['min_years' => 1, 'max_years' => 5, 'days' => 12],
            ['min_years' => 5, 'max_years' => 10, 'days' => 18],
            ['min_years' => 10, 'max_years' => null, 'days' => 30],
        ]);

        foreach ($tiers as $tier) {
            $maxYears = $tier['max_years'] ?? PHP_INT_MAX;
            if ($yearsOfService >= $tier['min_years'] && $yearsOfService <= $maxYears) {
                return $tier['days'];
            }
        }

        // Si no encaja en ningún tier, devolver el último (máximo)
        return $tiers[count($tiers) - 1]['days'] ?? 0;
    }

    /**
     * Calcula los años de servicio del empleado
     */
    public static function getYearsOfService(Employee $employee, ?Carbon $asOfDate = null): int
    {
        if (!$employee->hire_date) {
            return 0;
        }

        $asOfDate = $asOfDate ?? Carbon::now();
        return $employee->hire_date->diffInYears($asOfDate);
    }

    /**
     * Aprueba una solicitud de vacaciones y actualiza el balance en una transacción.
     */
    public static function approve(Vacation $vacation): void
    {
        DB::transaction(function () use ($vacation) {
            if ($vacation->vacation_balance_id && $vacation->vacationBalance) {
                $vacation->vacationBalance->confirmDays($vacation->business_days ?? 0);
            }
            $vacation->update(['status' => 'approved']);
        });
    }

    /**
     * Rechaza una solicitud de vacaciones y libera los días pendientes en una transacción.
     */
    public static function reject(Vacation $vacation): void
    {
        DB::transaction(function () use ($vacation) {
            if ($vacation->vacation_balance_id && $vacation->vacationBalance) {
                $vacation->vacationBalance->releasePendingDays($vacation->business_days ?? 0);
            }
            $vacation->update(['status' => 'rejected']);
        });
    }

    /**
     * Libera los días del balance al eliminar una vacación (pendiente o aprobada).
     */
    public static function releaseOnDelete(Vacation $vacation): void
    {
        DB::transaction(function () use ($vacation) {
            if (!$vacation->vacation_balance_id || !$vacation->vacationBalance) {
                return;
            }

            if ($vacation->isPending()) {
                $vacation->vacationBalance->releasePendingDays($vacation->business_days ?? 0);
            } elseif ($vacation->isApproved()) {
                $vacation->vacationBalance->returnUsedDays($vacation->business_days ?? 0);
            }
        });
    }

    /**
     * Obtiene o crea el balance de vacaciones del empleado para un año
     */
    public static function getOrCreateBalance(Employee $employee, int $year): VacationBalance
    {
        $balance = VacationBalance::where('employee_id', $employee->id)
            ->where('year', $year)
            ->first();

        if (!$balance) {
            $yearsOfService = self::getYearsOfService($employee, Carbon::create($year, 1, 1));
            $entitledDays = self::getEntitledDays($yearsOfService);

            $balance = VacationBalance::create([
                'employee_id' => $employee->id,
                'year' => $year,
                'years_of_service' => $yearsOfService,
                'entitled_days' => $entitledDays,
                'used_days' => 0,
                'pending_days' => 0,
            ]);
        }

        return $balance;
    }

    /**
     * Calcula los días hábiles entre dos fechas.
     * Usa vacation_business_days de la configuración (default: lunes a sábado), excluyendo feriados.
     */
    public static function calculateBusinessDays(Employee $employee, Carbon $startDate, Carbon $endDate): int
    {
        $businessDays = 0;
        $period = CarbonPeriod::create($startDate, $endDate);
        $workingDays = app(PayrollSettings::class)->vacation_business_days;

        $holidays = Holiday::whereBetween('date', [$startDate, $endDate])
            ->pluck('date')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->toArray();

        foreach ($period as $date) {
            if (in_array($date->format('Y-m-d'), $holidays)) {
                continue;
            }

            if (in_array($date->dayOfWeekIso, $workingDays)) {
                $businessDays++;
            }
        }

        return $businessDays;
    }

    /**
     * Verifica si una fecha es día laboral para el empleado (usado fuera del contexto de vacaciones)
     */
    public static function isWorkDay(Employee $employee, Carbon $date): bool
    {
        $schedule = $employee->getScheduleForDate($date);

        if (!$schedule) {
            $dayOfWeek = $date->dayOfWeekIso;
            return $dayOfWeek >= 1 && $dayOfWeek <= 6;
        }

        return !$schedule->isDayOff($date->dayOfWeekIso);
    }

    /**
     * Calcula la fecha de reintegro (primer día hábil después de end_date).
     * Usa vacation_business_days de la configuración, excluyendo feriados.
     */
    public static function calculateReturnDate(Employee $employee, Carbon $endDate): Carbon
    {
        $returnDate = $endDate->copy()->addDay();
        $workingDays = app(PayrollSettings::class)->vacation_business_days;
        $maxIterations = 30;
        $iterations = 0;

        while ($iterations < $maxIterations) {
            if (!Holiday::isHoliday($returnDate) && in_array($returnDate->dayOfWeekIso, $workingDays)) {
                return $returnDate;
            }

            $returnDate->addDay();
            $iterations++;
        }

        return $endDate->copy()->addDay();
    }

    /**
     * Valida una solicitud de vacaciones
     */
    public static function validateRequest(Employee $employee, Carbon $startDate, Carbon $endDate, ?Vacation $excludeVacation = null): array
    {
        $errors = [];
        $warnings = [];

        // 1. Verificar antigüedad mínima
        $minYearsService = app(PayrollSettings::class)->vacation_min_years_service;
        $yearsOfService = self::getYearsOfService($employee);
        if ($yearsOfService < $minYearsService) {
            $errors[] = "El empleado debe tener al menos {$minYearsService} año(s) de antigüedad para solicitar vacaciones. Antigüedad actual: {$employee->antiquity_description}";
        }

        // 2. Calcular días hábiles
        $businessDays = self::calculateBusinessDays($employee, $startDate, $endDate);

        if ($businessDays < 1) {
            $errors[] = "El período seleccionado no contiene días hábiles.";
        }

        // 3. Verificar mínimo de días consecutivos (fraccionamiento)
        // Solo advertencia, no error
        $minConsecutiveDays = app(PayrollSettings::class)->vacation_min_consecutive_days;
        if ($businessDays > 0 && $businessDays < $minConsecutiveDays) {
            $warnings[] = "Según la ley, el fraccionamiento mínimo es de {$minConsecutiveDays} días hábiles consecutivos. Se solicitaron {$businessDays} días.";
        }

        // 4. Verificar días disponibles
        $year = $startDate->year;
        $balance = self::getOrCreateBalance($employee, $year);

        // Si estamos editando, excluir los días de la vacación actual
        $currentPendingDays = 0;
        if ($excludeVacation && $excludeVacation->vacation_balance_id === $balance->id) {
            $currentPendingDays = $excludeVacation->business_days ?? 0;
        }

        $availableDays = $balance->available_days + $currentPendingDays;

        if ($businessDays > $availableDays) {
            $errors[] = "No hay suficientes días disponibles. Solicitados: {$businessDays}, Disponibles: {$availableDays}";
        }

        // 5. Verificar solapamiento con otras vacaciones
        $overlapping = Vacation::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            });

        if ($excludeVacation) {
            $overlapping->where('id', '!=', $excludeVacation->id);
        }

        if ($overlapping->exists()) {
            $errors[] = "El período seleccionado se solapa con otra solicitud de vacaciones.";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'business_days' => $businessDays,
            'available_days' => $availableDays,
            'balance' => $balance,
        ];
    }

    /**
     * Genera balances para todos los empleados activos de un año
     */
    public static function generateBalancesForYear(int $year): array
    {
        $employees = Employee::where('status', 'active')->get();
        $created = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            $existing = VacationBalance::where('employee_id', $employee->id)
                ->where('year', $year)
                ->exists();

            if (!$existing) {
                self::getOrCreateBalance($employee, $year);
                $created++;
            } else {
                $skipped++;
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'total' => $employees->count(),
        ];
    }

    /**
     * Recalcula el balance de un empleado basado en sus vacaciones aprobadas
     */
    public static function recalculateBalance(VacationBalance $balance): void
    {
        $usedDays = Vacation::where('vacation_balance_id', $balance->id)
            ->where('status', 'approved')
            ->sum('business_days');

        $pendingDays = Vacation::where('vacation_balance_id', $balance->id)
            ->where('status', 'pending')
            ->sum('business_days');

        $balance->update([
            'used_days' => $usedDays ?? 0,
            'pending_days' => $pendingDays ?? 0,
        ]);
    }
}
