<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ScheduleDay;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ContractController extends Controller
{
    /**
     * Genera y muestra el PDF del contrato laboral en el navegador (stream).
     */
    public function show(Contract $contract)
    {
        if (! $contract->start_date) {
            abort(422, 'El contrato no tiene fecha de inicio definida.');
        }

        $contract->load(['employee.branch.company', 'employee.schedule.days.breaks', 'employee.addresses.city', 'position', 'department']);

        $company = $contract->employee?->company;

        $logoPath = $company?->logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;

        // Datos del horario del empleado vigente en la fecha de inicio del contrato
        $schedule = $contract->employee?->getScheduleForDate($contract->start_date);
        $schedule?->loadMissing('days.breaks');
        $scheduleDays = $schedule ? $schedule->days : collect();

        $weekdayDay = $scheduleDays->filter(fn ($d) => $d->day_of_week >= 1 && $d->day_of_week <= 5)->first();
        $saturdayDay = $scheduleDays->firstWhere('day_of_week', 6);
        $breakMinutes = $weekdayDay ? $weekdayDay->total_break_minutes : 0;

        $shiftTypeLabel = match ($schedule?->shift_type) {
            'nocturno' => 'NOCTURNA',
            'mixto' => 'MIXTA',
            default => 'DIURNA',
        };

        $weeklyHours = $schedule
            ? (int) round($scheduleDays->where('is_active', true)->sum(fn ($d) => $d->scheduled_hours))
            : app(\App\Settings\PayrollSettings::class)->daily_hours * 6;
        $weeklyHoursInWords = self::numberToWords($weeklyHours);

        $startTime = $weekdayDay?->start_time
            ? Carbon::parse($weekdayDay->start_time)->format('H:i')
            : null;
        $endTime = $weekdayDay?->end_time
            ? Carbon::parse($weekdayDay->end_time)->format('H:i')
            : null;
        $dailyHours = $weekdayDay ? (int) $weekdayDay->scheduled_hours : null;
        $activeDayNums = $scheduleDays->where('is_active', true)->sortBy('day_of_week')->pluck('day_of_week');
        $dayNames = ScheduleDay::getDayOptions();
        $diasLaborales = $activeDayNums->isNotEmpty()
            ? ($activeDayNums->count() === 1
                ? ($dayNames[$activeDayNums->first()] ?? null)
                : ($dayNames[$activeDayNums->first()] ?? '').' a '.($dayNames[$activeDayNums->last()] ?? ''))
            : null;
        $tiempoDescanso = self::formatBreakTime($breakMinutes);

        $weekdayBreaks = $weekdayDay ? $weekdayDay->breaks->sortBy('start_time') : collect();
        $horaDescansoInicio = $weekdayBreaks->isNotEmpty()
            ? Carbon::parse($weekdayBreaks->first()->start_time)->format('H:i')
            : null;
        $horaDescansoFin = $weekdayBreaks->isNotEmpty()
            ? Carbon::parse($weekdayBreaks->last()->end_time)->format('H:i')
            : null;
        $horarioDescanso = $weekdayBreaks->isNotEmpty()
            ? $weekdayBreaks->map(fn ($b) => Carbon::parse($b->start_time)->format('H:i').' a '.Carbon::parse($b->end_time)->format('H:i'))->implode(' y ')
            : null;

        $employeeAge = $contract->employee?->birth_date
            ? $contract->employee->birth_date->age
            : null;

        $yearInWords = self::numberToWords($contract->start_date->year);
        $trialDaysInWords = self::numberToWords((int) ($contract->trial_days ?? 0));

        $durationDescription = $contract->end_date
            ? self::getDurationDescription($contract->start_date, $contract->end_date)
            : '';

        $employee = $contract->employee;
        $genderMap = ['masculino' => 'Masculino', 'femenino' => 'Femenino'];

        $principalAddress = $employee?->addresses->firstWhere('type', 'principal')
            ?? $employee?->addresses->first();
        $addressParts = array_filter([
            $principalAddress?->street,
            $principalAddress?->neighborhood,
            $principalAddress?->city?->name,
        ]);
        $formattedAddress = $addressParts ? implode(', ', $addressParts) : null;

        // Construir mapa de variables para reemplazar tokens en las secciones de la plantilla
        $vars = self::buildVarsMap(
            contract: $contract,
            company: $company,
            employee: $employee,
            genderMap: $genderMap,
            yearInWords: $yearInWords,
            weeklyHours: $weeklyHours,
            weeklyHoursInWords: $weeklyHoursInWords,
            shiftTypeLabel: $shiftTypeLabel,
            trialDaysInWords: $trialDaysInWords,
            durationDescription: $durationDescription,
            employeeAge: $employeeAge,
            formattedAddress: $formattedAddress,
            startTime: $startTime,
            endTime: $endTime,
            dailyHours: $dailyHours,
            diasLaborales: $diasLaborales,
            tiempoDescanso: $tiempoDescanso,
            horaDescansoInicio: $horaDescansoInicio,
            horaDescansoFin: $horaDescansoFin,
            horarioDescanso: $horarioDescanso,
        );

        // Resolver secciones de la plantilla (si existe), con scope por empresa
        $companyId = $company?->id;
        $template = ContractTemplate::getForType($contract->type, $companyId);
        $introText = $template?->intro_text
            ? ContractTemplate::resolveVariables($template->intro_text, $vars)
            : null;
        $closingText = $template?->closing_text
            ? ContractTemplate::resolveVariables($template->closing_text, $vars)
            : null;
        $signatureNotes = $template?->signature_notes
            ? ContractTemplate::resolveVariables($template->signature_notes, $vars)
            : null;

        // Body siempre desde la plantilla con variables resueltas
        $contractBody = ($template && $template->body)
            ? ContractTemplate::resolveVariables($template->body, $vars)
            : null;

        // Cláusulas adicionales del contrato (si las tiene)
        $additionalClauses = $contract->additional_clauses
            ? ContractTemplate::resolveVariables($contract->additional_clauses, $vars)
            : null;

        $pdf = Pdf::loadView('pdf.contract', [
            'contract' => $contract,
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? '',
            'companyRuc' => $company?->ruc ?? '',
            'companyAddress' => $company?->address ?? '',
            'companyPhone' => $company?->phone ?? '',
            'companyEmail' => $company?->email ?? '',
            'employerNumber' => $company?->employer_number ?? '',
            'city' => $company?->city ?? '',
            // Secciones resueltas desde la plantilla
            'introText' => $introText,
            'closingText' => $closingText,
            'signatureNotes' => $signatureNotes,
            'contractBody' => $contractBody,
            'additionalClauses' => $additionalClauses,
            // Presentación
            'showHeader' => $template ? (bool) $template->show_header : true,
            'showFooter' => $template ? (bool) $template->show_footer : true,
            'documentTitle' => $template?->document_title ?: null,
            'documentSubtitle' => $template?->document_subtitle ?: null,
            'documentArtReference' => $template ? ($template->document_art_reference ?? null) : null,
            'signatureEmployeeLabel' => $template?->signature_employee_label ?: null,
            'signatureEmployerLabel' => $template?->signature_employer_label ?: null,
            'signatureEmployerSublabel' => $template?->signature_employer_sublabel ?: null,
        ])->setPaper('a4', 'portrait');

        $employeeCi = $contract->employee?->ci ?? 'sin_ci';

        $response = $pdf->stream("contrato_{$employeeCi}_{$contract->start_date->format('Y_m_d')}.pdf");
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    /**
     * Genera un PDF de vista previa de la plantilla usando datos de muestra.
     */
    public function previewTemplate(ContractTemplate $contractTemplate)
    {
        $sampleVars = self::buildSampleVarsMap();

        $introText = $contractTemplate->intro_text
            ? ContractTemplate::resolveVariables($contractTemplate->intro_text, $sampleVars)
            : null;
        $closingText = $contractTemplate->closing_text
            ? ContractTemplate::resolveVariables($contractTemplate->closing_text, $sampleVars)
            : null;
        $signatureNotes = $contractTemplate->signature_notes
            ? ContractTemplate::resolveVariables($contractTemplate->signature_notes, $sampleVars)
            : null;
        // Objeto mock para que el blade funcione con datos ficticios
        $fakeEmployee = new \stdClass;
        $fakeEmployee->full_name = 'MARÍA GÓMEZ MARTÍNEZ';
        $fakeEmployee->ci = '2.345.678';

        $fakeContract = new \stdClass;
        $fakeContract->type = $contractTemplate->type;
        $fakeContract->id = 'VISTA PREVIA';
        $fakeContract->employee = $fakeEmployee;
        $fakeContract->start_date = Carbon::parse('2025-01-01');
        $fakeContract->end_date = Carbon::parse('2025-12-31');
        $fakeContract->trial_days = 90;
        $fakeContract->salary = 2500000;
        $fakeContract->position = null;

        $pdf = Pdf::loadView('pdf.contract', [
            'contract' => $fakeContract,
            'companyLogo' => null,
            'companyName' => 'EMPRESA DE EJEMPLO S.A.',
            'companyRuc' => '80012345-6',
            'companyAddress' => 'Av. España 1234',
            'companyPhone' => '0981123456',
            'companyEmail' => 'empresa@ejemplo.com.py',
            'employerNumber' => '12345678',
            'city' => 'Asunción',
            // Secciones resueltas desde la plantilla
            'introText' => $introText,
            'closingText' => $closingText,
            'signatureNotes' => $signatureNotes,
            'contractBody' => $contractTemplate->body
                ? ContractTemplate::resolveVariables($contractTemplate->body, $sampleVars)
                : null,
            'additionalClauses' => null,
            // Presentación
            'showHeader' => (bool) $contractTemplate->show_header,
            'showFooter' => (bool) $contractTemplate->show_footer,
            'documentTitle' => $contractTemplate->document_title ?: null,
            'documentSubtitle' => $contractTemplate->document_subtitle ?: null,
            'documentArtReference' => $contractTemplate->document_art_reference ?? null,
            'signatureEmployeeLabel' => $contractTemplate->signature_employee_label ?: null,
            'signatureEmployerLabel' => $contractTemplate->signature_employer_label ?: null,
            'signatureEmployerSublabel' => $contractTemplate->signature_employer_sublabel ?: null,
        ])->setPaper('a4', 'portrait');

        $response = $pdf->stream("preview_plantilla_{$contractTemplate->type}.pdf");
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    /**
     * Construye el mapa de variables resueltas desde los datos reales del contrato.
     *
     * @return array<string, string>
     */
    private static function buildVarsMap(
        Contract $contract,
        mixed $company,
        mixed $employee,
        array $genderMap,
        string $yearInWords,
        int $weeklyHours,
        string $weeklyHoursInWords,
        string $shiftTypeLabel,
        string $trialDaysInWords,
        string $durationDescription,
        ?int $employeeAge,
        ?string $formattedAddress = null,
        ?string $startTime = null,
        ?string $endTime = null,
        ?int $dailyHours = null,
        ?string $diasLaborales = null,
        ?string $tiempoDescanso = null,
        ?string $horaDescansoInicio = null,
        ?string $horaDescansoFin = null,
        ?string $horarioDescanso = null,
    ): array {
        return [
            '{ciudad}' => $company?->city ?? '.............................',
            '{dia}' => $contract->start_date->format('d'),
            '{mes}' => strtoupper($contract->start_date->translatedFormat('F')),
            '{año}' => strtoupper($yearInWords),
            '{representante_legal}' => $company?->legal_rep_name ?? '......................................',
            '{ci_representante}' => $company?->legal_rep_ci ?? '.......................',
            '{nombre_empresa}' => $company?->name ?? '......................................',
            '{ruc_empresa}' => $company?->ruc ?? '.......................',
            '{domicilio_empresa}' => $company?->address ?? '......................................................................................................',
            '{nombre_empleado}' => strtoupper($employee?->full_name ?? '......................................................................'),
            '{ci_empleado}' => $employee?->ci ?? '.......................',
            '{edad_empleado}' => (string) ($employeeAge ?? '......'),
            '{sexo_empleado}' => $genderMap[$employee?->gender] ?? '...................',
            '{estado_civil_empleado}' => $employee?->marital_status_label ?? '...................',
            '{cargo}' => $contract->position?->name ?? '.....................................',
            '{nacionalidad_empleado}' => $employee?->nationality ?? '...................',
            '{domicilio_empleado}' => $formattedAddress ?? '......................................................................................................',
            '{salario}' => $contract->salary !== null ? number_format((float) $contract->salary, 0, ',', '.') : '...................',
            '{salario_en_palabras}' => $contract->salary !== null ? self::numberToWords((int) $contract->salary).' guaranies' : '...................',
            '{tipo_jornada}' => $shiftTypeLabel,
            '{horas_semanales}' => (string) $weeklyHours,
            '{horas_semanales_en_palabras}' => $weeklyHoursInWords,
            '{dias_prueba}' => (string) ((int) ($contract->trial_days ?? 0)),
            '{dias_prueba_en_palabras}' => $trialDaysInWords,
            '{duracion_contrato}' => $durationDescription ?: '......',
            '{tipo_contrato}' => Contract::getTypeLabel($contract->type),
            '{fecha_inicio}' => $contract->start_date->format('d/m/Y'),
            '{fecha_fin}' => $contract->end_date?->format('d/m/Y') ?? 'INDEFINIDO',
            '{modalidad}' => $contract->work_modality ? Contract::getWorkModalityLabel($contract->work_modality) : '...................',
            '{metodo_pago}' => match ($contract->payment_method) {
                'cash' => 'Efectivo',
                'debit' => 'Débito bancario',
                'check' => 'Cheque',
                default => $contract->payment_method ?? '...................',
            },
            '{tipo_salario}' => $contract->salary_type ? Contract::getSalaryTypeLabel($contract->salary_type) : '...................',
            '{departamento}' => $contract->department?->name ?? $contract->position?->department?->name ?? '...................',
            '{sucursal}' => $employee?->branch?->name ?? '...................',
            '{fecha_nacimiento_empleado}' => $employee?->birth_date?->format('d/m/Y') ?? '...................',
            '{telefono_empleado}' => $employee?->phone ?? '...................',
            '{hora_entrada}' => $startTime ?? '...................',
            '{hora_salida}' => $endTime ?? '...................',
            '{horas_diarias}' => $dailyHours !== null ? (string) $dailyHours : '...................',
            '{dias_laborales}' => $diasLaborales ?? '...................',
            '{tiempo_descanso}' => $tiempoDescanso ?? '...................',
            '{hora_descanso_inicio}' => $horaDescansoInicio ?? '...................',
            '{hora_descanso_fin}' => $horaDescansoFin ?? '...................',
            '{horario_descanso}' => $horarioDescanso ?? '...................',
        ];
    }

    /**
     * Construye el mapa de variables con datos ficticios para la vista previa de plantilla.
     *
     * @return array<string, string>
     */
    private static function buildSampleVarsMap(): array
    {
        return [
            '{ciudad}' => 'Asunción',
            '{dia}' => '01',
            '{mes}' => 'ENERO',
            '{año}' => 'DOS MIL VEINTICINCO',
            '{representante_legal}' => 'JUAN PÉREZ GARCÍA',
            '{ci_representante}' => '1.234.567',
            '{nombre_empresa}' => 'EMPRESA DE EJEMPLO S.A.',
            '{ruc_empresa}' => '80012345-6',
            '{domicilio_empresa}' => 'Av. España 1234, Asunción',
            '{nombre_empleado}' => 'MARÍA GÓMEZ MARTÍNEZ',
            '{ci_empleado}' => '2.345.678',
            '{edad_empleado}' => '28',
            '{sexo_empleado}' => 'Femenino',
            '{estado_civil_empleado}' => 'Soltera',
            '{cargo}' => 'Asistente Administrativo',
            '{nacionalidad_empleado}' => 'Paraguaya',
            '{domicilio_empleado}' => 'Calle Mayor 456, Asunción',
            '{salario}' => '2.500.000',
            '{salario_en_palabras}' => 'dos millones quinientos mil guaranies',
            '{tipo_jornada}' => 'DIURNA',
            '{horas_semanales}' => '48',
            '{horas_semanales_en_palabras}' => 'cuarenta y ocho',
            '{dias_prueba}' => '90',
            '{dias_prueba_en_palabras}' => 'noventa',
            '{duracion_contrato}' => 'un (1) año',
            '{tipo_contrato}' => 'Por Tiempo Indefinido',
            '{fecha_inicio}' => '01/01/2025',
            '{fecha_fin}' => 'INDEFINIDO',
            '{modalidad}' => 'Presencial',
            '{metodo_pago}' => 'Débito bancario',
            '{tipo_salario}' => 'Mensualizado (Sueldo)',
            '{departamento}' => 'Administración',
            '{sucursal}' => 'Sucursal Central',
            '{fecha_nacimiento_empleado}' => '15/03/1995',
            '{telefono_empleado}' => '0981123456',
            '{hora_entrada}' => '07:00',
            '{hora_salida}' => '16:00',
            '{horas_diarias}' => '8',
            '{dias_laborales}' => 'Lunes a Viernes',
            '{tiempo_descanso}' => '1 hora',
            '{hora_descanso_inicio}' => '12:00',
            '{hora_descanso_fin}' => '13:00',
            '{horario_descanso}' => '12:00 a 13:00',
        ];
    }

    /**
     * Formatea los minutos de descanso como texto legible (ej: "1 hora", "30 minutos", "1 hora y 30 minutos").
     */
    private static function formatBreakTime(int $minutes): string
    {
        if ($minutes === 0) {
            return 'Sin descanso';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours === 1 ? '1 hora' : "{$hours} horas";
        }

        if ($remaining > 0) {
            $parts[] = "{$remaining} minutos";
        }

        return implode(' y ', $parts);
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
            $word = self::numberToWords($diff->y);
            $parts[] = $word.' ('.$diff->y.') '.($diff->y === 1 ? 'año' : 'años');
        }

        if ($diff->m > 0) {
            $word = self::numberToWords($diff->m);
            $parts[] = $word.' ('.$diff->m.') '.($diff->m === 1 ? 'mes' : 'meses');
        }

        if ($diff->d > 0 && $diff->y === 0) {
            $word = self::numberToWords($diff->d);
            $parts[] = $word.' ('.$diff->d.') '.($diff->d === 1 ? 'día' : 'días');
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

        $units = ['', 'un', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
        $teens = ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciseis', 'diecisiete', 'dieciocho', 'diecinueve'];
        $tens = ['', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $hundreds = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

        $result = '';

        if ($number >= 1000000) {
            $millions = (int) ($number / 1000000);
            if ($millions === 1) {
                $result .= 'un millon ';
            } else {
                $result .= self::numberToWords($millions).' millones ';
            }
            $number %= 1000000;
        }

        if ($number >= 1000) {
            $thousands = (int) ($number / 1000);
            if ($thousands === 1) {
                $result .= 'mil ';
            } else {
                $result .= self::numberToWords($thousands).' mil ';
            }
            $number %= 1000;
        }

        if ($number >= 100) {
            if ($number === 100) {
                $result .= 'cien';

                return trim($result);
            }
            $result .= $hundreds[(int) ($number / 100)].' ';
            $number %= 100;
        }

        if ($number >= 21 && $number <= 29) {
            $veinti = ['', 'veintiun', 'veintidos', 'veintitres', 'veinticuatro', 'veinticinco', 'veintiseis', 'veintisiete', 'veintiocho', 'veintinueve'];
            $result .= $veinti[$number - 20];
        } elseif ($number >= 30) {
            $result .= $tens[(int) ($number / 10)];
            if ($number % 10 > 0) {
                $result .= ' y '.$units[$number % 10];
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
