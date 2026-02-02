<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen de Asistencia</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            padding: 15mm 20mm;
        }

        .company-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #000;
        }

        .company-logo {
            max-height: 40px;
            max-width: 120px;
            margin-bottom: 8px;
        }

        .company-name {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .company-info {
            font-size: 9px;
        }

        .title {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 20px 0 5px 0;
        }

        .subtitle {
            text-align: center;
            font-size: 10px;
            margin-bottom: 20px;
        }

        .section {
            margin-bottom: 15px;
        }

        .section-title {
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            padding: 5px 0;
            margin-bottom: 8px;
            border-bottom: 1px solid #000;
        }

        .info-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }

        .info-row {
            display: table-row;
        }

        .info-label {
            display: table-cell;
            font-weight: bold;
            width: 180px;
            padding: 5px 8px;
            border: 1px solid #000;
        }

        .info-value {
            display: table-cell;
            padding: 5px 8px;
            border: 1px solid #000;
        }

        .metrics-section {
            margin: 15px 0;
            padding: 12px;
            border: 1px solid #000;
        }

        .metrics-title {
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
            font-size: 10px;
        }

        .metrics-grid {
            display: table;
            width: 100%;
        }

        .metric-item {
            display: table-cell;
            width: 33.33%;
            text-align: center;
            padding: 8px;
            border-right: 1px solid #ccc;
        }

        .metric-item:last-child {
            border-right: none;
        }

        .metric-value {
            font-size: 16px;
            font-weight: bold;
        }

        .metric-label {
            font-size: 9px;
            margin-top: 3px;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
            font-size: 9px;
        }

        th {
            font-weight: bold;
            background-color: #f5f5f5;
        }

        .text-left {
            text-align: left;
        }

        .text-center {
            text-align: center;
        }

        .text-bold {
            font-weight: bold;
        }

        .note-section {
            margin: 15px 0;
            padding: 12px;
            border: 1px solid #000;
        }

        .note-title {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .status-present {
            color: #065f46;
        }

        .status-absent {
            color: #991b1b;
        }

        .status-on_leave {
            color: #92400e;
        }

        .status-holiday,
        .status-weekend {
            color: #1e40af;
        }

        .signature-section {
            margin-top: 50px;
            display: table;
            width: 100%;
        }

        .signature-item {
            display: table-cell;
            width: 50%;
            text-align: center;
            padding: 0 30px;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-bottom: 5px;
            padding-top: 5px;
        }

        .signature-label {
            font-size: 10px;
            font-weight: bold;
        }

        .signature-sublabel {
            font-size: 9px;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 8px;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
    </style>
</head>

<body>
    @php
        $statusLabels = [
            'present' => 'Presente',
            'absent' => 'Ausente',
            'on_leave' => 'De Permiso',
            'holiday' => 'Feriado',
            'weekend' => 'Fin de Semana',
        ];
    @endphp

    {{-- Encabezado de la Empresa --}}
    <div class="company-header">
        @if ($companyLogo)
            <img src="{{ $companyLogo }}" alt="Logo" class="company-logo">
        @endif
        <div class="company-name">{{ $companyName }}</div>
        <div class="company-info">
            @if ($companyRuc)
                RUC: {{ $companyRuc }}
            @endif
            @if ($employerNumber)
                | Nro. Patronal: {{ $employerNumber }}
            @endif
        </div>
        @if ($companyAddress)
            <div class="company-info">{{ $companyAddress }}</div>
        @endif
        @if ($companyPhone || $companyEmail)
            <div class="company-info">
                @if ($companyPhone)
                    Tel: {{ $companyPhone }}
                @endif
                @if ($companyPhone && $companyEmail)
                    |
                @endif
                @if ($companyEmail)
                    {{ $companyEmail }}
                @endif
            </div>
        @endif
    </div>

    {{-- Titulo --}}
    <div class="title">Resumen de Asistencia Diario</div>
    <div class="subtitle">{{ $attendanceDay->date->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</div>

    {{-- Informacion del Empleado --}}
    <div class="section">
        <div class="section-title">Informacion del Empleado</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nombre Completo:</div>
                <div class="info-value">{{ $attendanceDay->employee->full_name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Cedula de Identidad:</div>
                <div class="info-value">{{ $attendanceDay->employee->ci }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Cargo:</div>
                <div class="info-value">{{ $attendanceDay->employee->position->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Departamento:</div>
                <div class="info-value">{{ $attendanceDay->employee->position->department->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Estado del Dia:</div>
                <div class="info-value">
                    <strong class="status-{{ $attendanceDay->status }}">
                        {{ $statusLabels[$attendanceDay->status] ?? $attendanceDay->status }}
                    </strong>
                </div>
            </div>
        </div>
    </div>

    {{-- Metricas Principales --}}
    @if ($attendanceDay->status === 'present')
        <div class="metrics-section">
            <div class="metrics-title">Resumen de Horas</div>
            <div class="metrics-grid">
                <div class="metric-item">
                    <div class="metric-value">{{ $attendanceDay->total_hours ?? 0 }}</div>
                    <div class="metric-label">Horas Totales</div>
                </div>
                <div class="metric-item">
                    <div class="metric-value">{{ $attendanceDay->net_hours ?? 0 }}</div>
                    <div class="metric-label">Horas Netas</div>
                </div>
                <div class="metric-item">
                    <div class="metric-value">{{ $attendanceDay->extra_hours ?? 0 }}</div>
                    <div class="metric-label">Horas Extra</div>
                </div>
            </div>
        </div>
    @endif

    {{-- Tabla de Horarios --}}
    <div class="section">
        <div class="section-title">Horarios y Asistencia</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 30%;" class="text-left">Concepto</th>
                    <th style="width: 25%;">Esperado</th>
                    <th style="width: 25%;">Registrado</th>
                    <th style="width: 20%;">Diferencia</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-left text-bold">Entrada</td>
                    <td>{{ $attendanceDay->expected_check_in ?? '-' }}</td>
                    <td>{{ $attendanceDay->check_in_time ?? '-' }}</td>
                    <td>
                        @if ($attendanceDay->late_minutes > 0)
                            +{{ $attendanceDay->late_minutes }} min
                        @else
                            -
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="text-left text-bold">Salida</td>
                    <td>{{ $attendanceDay->expected_check_out ?? '-' }}</td>
                    <td>{{ $attendanceDay->check_out_time ?? '-' }}</td>
                    <td>
                        @if ($attendanceDay->early_leave_minutes > 0)
                            -{{ $attendanceDay->early_leave_minutes }} min
                        @else
                            -
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="text-left text-bold">Descanso</td>
                    <td>{{ $attendanceDay->expected_break_minutes ?? 0 }} min</td>
                    <td>{{ $attendanceDay->break_minutes ?? 0 }} min</td>
                    <td>
                        @php
                            $breakDiff = ($attendanceDay->break_minutes ?? 0) - ($attendanceDay->expected_break_minutes ?? 0);
                        @endphp
                        @if ($breakDiff > 0)
                            +{{ $breakDiff }} min
                        @elseif($breakDiff < 0)
                            {{ $breakDiff }} min
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @if ($attendanceDay->status === 'present')
                    <tr>
                        <td class="text-left text-bold">Total Horas</td>
                        <td>{{ $attendanceDay->expected_hours ?? 0 }} hrs</td>
                        <td>{{ $attendanceDay->total_hours ?? 0 }} hrs</td>
                        <td>
                            @php
                                $hoursDiff = ($attendanceDay->total_hours ?? 0) - ($attendanceDay->expected_hours ?? 0);
                            @endphp
                            @if ($hoursDiff > 0)
                                +{{ number_format($hoursDiff, 1) }} hrs
                            @elseif($hoursDiff < 0)
                                {{ number_format($hoursDiff, 1) }} hrs
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    {{-- Eventos de Marcacion --}}
    @if ($attendanceDay->events->isNotEmpty())
        <div class="section">
            <div class="section-title">Eventos de Marcacion</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 25%;">Hora</th>
                        <th style="width: 75%;" class="text-left">Tipo de Evento</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($attendanceDay->events->sortBy('recorded_at') as $event)
                        <tr>
                            <td class="text-bold">
                                {{ \Carbon\Carbon::parse($event->recorded_at)->format('H:i:s') }}
                            </td>
                            <td class="text-left">
                                @switch($event->event_type)
                                    @case('check_in')
                                        Entrada a la jornada
                                    @break

                                    @case('check_out')
                                        Salida de la jornada
                                    @break

                                    @case('break_start')
                                        Inicio de descanso
                                    @break

                                    @case('break_end')
                                        Fin de descanso
                                    @break

                                    @default
                                        Otro evento
                                @endswitch
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Notas --}}
    @if ($attendanceDay->notes)
        <div class="note-section">
            <div class="note-title">Observaciones:</div>
            <p>{{ $attendanceDay->notes }}</p>
        </div>
    @endif

    {{-- Firmas --}}
    <div class="signature-section">
        <div class="signature-item">
            <div class="signature-line"></div>
            <div class="signature-label">Empleado</div>
            <div class="signature-sublabel">{{ $attendanceDay->employee->full_name }}</div>
            <div class="signature-sublabel">CI: {{ $attendanceDay->employee->ci }}</div>
        </div>
        <div class="signature-item">
            <div class="signature-line"></div>
            <div class="signature-label">Recursos Humanos</div>
            <div class="signature-sublabel">Firma y Sello</div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        Documento generado el {{ now()->format('d/m/Y H:i') }}
        @if ($attendanceDay->is_calculated)
            | Calculado: {{ $attendanceDay->calculated_at?->format('d/m/Y H:i') }}
        @endif
        @if ($city)
            | {{ $city }}, Paraguay
        @endif
    </div>
</body>

</html>
