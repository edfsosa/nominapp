<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Contract;
use App\Settings\GeneralSettings;
use Barryvdh\DomPDF\Facade\Pdf;

class ContractController extends Controller
{
    /**
     * Genera y muestra el PDF del contrato laboral en el navegador (stream).
     */
    public function show(Contract $contract)
    {
        $contract->load(['employee.branch.company', 'employee.schedule.days.breaks', 'position', 'department']);

        $settings = app(GeneralSettings::class);
        $company  = $contract->employee?->company;

        $logoPath    = $company?->logo ?? $settings->company_logo;
        $companyLogo = $logoPath ? storage_path('app/public/' . $logoPath) : null;

        // Datos del horario del empleado vigente en la fecha de inicio del contrato
        $schedule    = $contract->employee?->getScheduleForDate($contract->start_date);
        $schedule?->loadMissing('days.breaks');
        $scheduleDays = $schedule ? $schedule->days : collect();

        $weekdayDay  = $scheduleDays->filter(fn($d) => $d->day_of_week >= 1 && $d->day_of_week <= 5)->first();
        $saturdayDay = $scheduleDays->firstWhere('day_of_week', 6);
        $breakMinutes = $weekdayDay ? $weekdayDay->total_break_minutes : 0;

        $shiftTypeLabel = match($schedule?->shift_type) {
            'nocturno' => 'NOCTURNA',
            'mixto'    => 'MIXTA',
            default    => 'DIURNA',
        };

        $weeklyHours        = $settings->working_hours_per_week;
        $weeklyHoursInWords = self::numberToWords($weeklyHours);

        $employeeAge = $contract->employee?->birth_date
            ? $contract->employee->birth_date->age
            : null;

        $yearInWords      = self::numberToWords($contract->start_date->year);
        $trialDaysInWords = self::numberToWords((int) ($contract->trial_days ?? 0));

        $durationDescription = $contract->end_date
            ? self::getDurationDescription($contract->start_date, $contract->end_date)
            : '';

        $pdf = Pdf::loadView('pdf.contract', [
            'contract'           => $contract,
            'companyLogo'        => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName'        => $company?->name ?? $settings->company_name,
            'companyRuc'         => $company?->ruc ?? $settings->company_ruc ?? '',
            'companyAddress'     => $company?->address ?? $settings->company_address ?? '',
            'companyPhone'       => $company?->phone ?? $settings->company_phone ?? '',
            'companyEmail'       => $company?->email ?? $settings->company_email ?? '',
            'employerNumber'     => $company?->employer_number ?? $settings->company_employer_number ?? '',
            'city'               => $company?->city ?? $settings->company_city ?? '',
            'salaryInWords'      => self::numberToWords((int) $contract->salary) . ' guaranies',
            'weekdayDay'         => $weekdayDay,
            'saturdayDay'        => $saturdayDay,
            'breakMinutes'       => $breakMinutes,
            'shiftTypeLabel'     => $shiftTypeLabel,
            'weeklyHours'        => $weeklyHours,
            'weeklyHoursInWords' => $weeklyHoursInWords,
            'employeeAge'        => $employeeAge,
            'yearInWords'        => $yearInWords,
            'durationDescription' => $durationDescription,
            'trialDaysInWords'   => $trialDaysInWords,
        ])->setPaper('a4', 'portrait');

        $employeeCi = $contract->employee?->ci ?? 'sin_ci';

        return $pdf->stream("contrato_{$employeeCi}_{$contract->start_date->format('Y_m_d')}.pdf");
    }

    /**
     * Calcula la duración entre dos fechas expresada en palabras.
     * Ejemplo: "seis (6) meses" o "un (1) año y tres (3) meses"
     */
    private static function getDurationDescription(Carbon $start, Carbon $end): string
    {
        $diff = $start->diff($end);

        $parts = [];

        if ($diff->y > 0) {
            $word   = self::numberToWords($diff->y);
            $parts[] = $word . ' (' . $diff->y . ') ' . ($diff->y === 1 ? 'año' : 'años');
        }

        if ($diff->m > 0) {
            $word   = self::numberToWords($diff->m);
            $parts[] = $word . ' (' . $diff->m . ') ' . ($diff->m === 1 ? 'mes' : 'meses');
        }

        if ($diff->d > 0 && $diff->y === 0) {
            $word   = self::numberToWords($diff->d);
            $parts[] = $word . ' (' . $diff->d . ') ' . ($diff->d === 1 ? 'día' : 'días');
        }

        return implode(' y ', $parts) ?: '......';
    }

    /**
     * Convierte un número a su representación en palabras en español.
     */
    private static function numberToWords(int $number): string
    {
        if ($number === 0) {
            return 'cero';
        }

        $units    = ['', 'un', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
        $teens    = ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciseis', 'diecisiete', 'dieciocho', 'diecinueve'];
        $tens     = ['', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $hundreds = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

        $result = '';

        if ($number >= 1000000) {
            $millions = (int) ($number / 1000000);
            if ($millions === 1) {
                $result .= 'un millon ';
            } else {
                $result .= self::numberToWords($millions) . ' millones ';
            }
            $number %= 1000000;
        }

        if ($number >= 1000) {
            $thousands = (int) ($number / 1000);
            if ($thousands === 1) {
                $result .= 'mil ';
            } else {
                $result .= self::numberToWords($thousands) . ' mil ';
            }
            $number %= 1000;
        }

        if ($number >= 100) {
            if ($number === 100) {
                $result .= 'cien';
                return trim($result);
            }
            $result .= $hundreds[(int) ($number / 100)] . ' ';
            $number %= 100;
        }

        if ($number >= 21 && $number <= 29) {
            $veinti = ['', 'veintiun', 'veintidos', 'veintitres', 'veinticuatro', 'veinticinco', 'veintiseis', 'veintisiete', 'veintiocho', 'veintinueve'];
            $result .= $veinti[$number - 20];
        } elseif ($number >= 30) {
            $result .= $tens[(int) ($number / 10)];
            if ($number % 10 > 0) {
                $result .= ' y ' . $units[$number % 10];
            }
        } elseif ($number === 20) {
            $result .= 'veinte';
        } elseif ($number >= 10) {
            $result .= $teens[$number - 10];
        } elseif ($number > 0) {
            $result .= $units[$number];
        }

        return trim($result);
    }
}
