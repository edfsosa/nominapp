<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Vacation;
use App\Models\VacationBalance;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class VacationService
{
    /**
     * Días de vacaciones según antigüedad (Ley Paraguaya)
     * - Hasta 5 años: 12 días hábiles
     * - Más de 5 a 10 años: 18 días hábiles
     * - Más de 10 años: 30 días hábiles
     */
    public static function getEntitledDays(int $yearsOfService): int
    {
        if ($yearsOfService < 1) {
            return 0;
        } elseif ($yearsOfService <= 5) {
            return 12;
        } elseif ($yearsOfService <= 10) {
            return 18;
        } else {
            return 30;
        }
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
     * Calcula los días hábiles entre dos fechas para un empleado
     * Días hábiles = Lunes a Sábado según horario del empleado, excluyendo feriados
     */
    public static function calculateBusinessDays(Employee $employee, Carbon $startDate, Carbon $endDate): int
    {
        $businessDays = 0;
        $period = CarbonPeriod::create($startDate, $endDate);

        // Obtener feriados en el rango
        $holidays = Holiday::whereBetween('date', [$startDate, $endDate])
            ->pluck('date')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->toArray();

        foreach ($period as $date) {
            // Verificar si es feriado
            if (in_array($date->format('Y-m-d'), $holidays)) {
                continue;
            }

            // Verificar si es día laboral según horario del empleado
            if (self::isWorkDay($employee, $date)) {
                $businessDays++;
            }
        }

        return $businessDays;
    }

    /**
     * Verifica si una fecha es día laboral para el empleado
     */
    public static function isWorkDay(Employee $employee, Carbon $date): bool
    {
        // Si no tiene horario asignado, asumir lunes a sábado
        if (!$employee->schedule) {
            $dayOfWeek = $date->dayOfWeekIso; // 1=Lunes, 7=Domingo
            return $dayOfWeek >= 1 && $dayOfWeek <= 6; // Lunes a Sábado
        }

        // Verificar según horario del empleado
        $dayOfWeek = $date->dayOfWeekIso;
        return !$employee->schedule->isDayOff($dayOfWeek);
    }

    /**
     * Calcula la fecha de reintegro (próximo día laboral después de end_date)
     * No puede ser domingo ni feriado
     */
    public static function calculateReturnDate(Employee $employee, Carbon $endDate): Carbon
    {
        $returnDate = $endDate->copy()->addDay();

        // Buscar el próximo día laboral
        $maxIterations = 30; // Evitar bucle infinito
        $iterations = 0;

        while ($iterations < $maxIterations) {
            // Verificar si es feriado
            if (Holiday::isHoliday($returnDate)) {
                $returnDate->addDay();
                $iterations++;
                continue;
            }

            // Verificar si es día laboral según horario
            if (self::isWorkDay($employee, $returnDate)) {
                return $returnDate;
            }

            $returnDate->addDay();
            $iterations++;
        }

        // Si no encuentra en 30 días, devolver el día siguiente al fin
        return $endDate->copy()->addDay();
    }

    /**
     * Valida una solicitud de vacaciones
     */
    public static function validateRequest(Employee $employee, Carbon $startDate, Carbon $endDate, ?Vacation $excludeVacation = null): array
    {
        $errors = [];
        $warnings = [];

        // 1. Verificar antigüedad mínima (1 año)
        $yearsOfService = self::getYearsOfService($employee);
        if ($yearsOfService < 1) {
            $errors[] = "El empleado debe tener al menos 1 año de antigüedad para solicitar vacaciones. Antigüedad actual: {$employee->antiquity_description}";
        }

        // 2. Calcular días hábiles
        $businessDays = self::calculateBusinessDays($employee, $startDate, $endDate);

        if ($businessDays < 1) {
            $errors[] = "El período seleccionado no contiene días hábiles.";
        }

        // 3. Verificar mínimo de 6 días consecutivos (fraccionamiento)
        // Solo advertencia, no error
        if ($businessDays > 0 && $businessDays < 6) {
            $warnings[] = "Según la ley, el fraccionamiento mínimo es de 6 días hábiles consecutivos. Se solicitaron {$businessDays} días.";
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
