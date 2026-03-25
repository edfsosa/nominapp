<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
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
            font-size: 10px;
            line-height: 1.4;
            color: #000;
            padding: 12mm 15mm;
        }

        /* Encabezado */
        .header {
            display: table;
            width: 100%;
            border-bottom: 1.5px solid #000;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .header-logo {
            display: table-cell;
            width: 80px;
            vertical-align: middle;
        }

        .header-logo img {
            max-height: 32px;
            max-width: 72px;
        }

        .header-company {
            display: table-cell;
            vertical-align: middle;
            padding-left: 8px;
        }

        .header-company .name {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .header-company .meta {
            font-size: 8px;
            color: #444;
            margin-top: 1px;
        }

        .header-title {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 160px;
        }

        .header-title .doc-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .header-title .doc-date {
            font-size: 8px;
            color: #444;
            margin-top: 2px;
        }

        /* Secciones */
        .section {
            margin-bottom: 7px;
        }

        .section-title {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
            margin-bottom: 4px;
        }

        /* Grilla de datos (clave: valor) */
        .data-grid {
            display: table;
            width: 100%;
        }

        .data-row {
            display: table-row;
        }

        .data-label {
            display: table-cell;
            width: 110px;
            font-weight: bold;
            padding: 2px 6px 2px 0;
            vertical-align: top;
        }

        .data-value {
            display: table-cell;
            padding: 2px 0;
            vertical-align: top;
        }

        /* Grilla de dos columnas */
        .two-col {
            display: table;
            width: 100%;
        }

        .col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 10px;
        }

        .col:last-child {
            padding-right: 0;
            padding-left: 10px;
            border-left: 1px solid #ddd;
        }

        /* Métricas compactas en línea */
        .metrics-row {
            display: table;
            width: 100%;
            border: 1px solid #ccc;
            margin-bottom: 7px;
        }

        .metric {
            display: table-cell;
            text-align: center;
            padding: 5px 4px;
            border-right: 1px solid #ccc;
        }

        .metric-last {
            border-right: none;
        }

        .metric-val {
            font-size: 13px;
            font-weight: bold;
        }

        .metric-lbl {
            font-size: 7px;
            text-transform: uppercase;
            color: #555;
            margin-top: 1px;
        }

        /* Tabla */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 7px;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 3px 5px;
            font-size: 8px;
        }

        th {
            font-weight: bold;
            background: #f0f0f0;
            text-align: center;
        }

        td {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .text-bold {
            font-weight: bold;
        }

        /* Notas */
        .notes-box {
            border: 1px solid #ccc;
            padding: 4px 6px;
            font-size: 8px;
            margin-bottom: 7px;
        }

        .notes-label {
            font-weight: bold;
            margin-bottom: 2px;
        }

        /* Firmas */
        .signature-row {
            display: table;
            width: 100%;
            margin-top: 20px;
        }

        .signature-cell {
            display: table-cell;
            width: 50%;
            text-align: center;
            padding: 0 24px;
        }

        .signature-line {
            border-top: 1px solid #000;
            padding-top: 3px;
            font-size: 8px;
            font-weight: bold;
        }

        .signature-sub {
            font-size: 7px;
            color: #444;
            margin-top: 1px;
        }

        /* Footer */
        .footer {
            margin-top: 10px;
            border-top: 1px solid #ccc;
            padding-top: 4px;
            font-size: 7px;
            color: #666;
            text-align: center;
        }
    </style>
</head>

<body>

    {{-- Encabezado --}}
    <div class="header">
        @if ($companyLogo)
            <div class="header-logo">
                <img src="{{ $companyLogo }}" alt="Logo">
            </div>
        @endif
        <div class="header-company">
            <div class="name">{{ $companyName }}</div>
            <div class="meta">
                @if ($companyRuc) RUC: {{ $companyRuc }} @endif
                @if ($companyRuc && $employerNumber) &nbsp;·&nbsp; @endif
                @if ($employerNumber) Nro. Patronal: {{ $employerNumber }} @endif
            </div>
            @if ($companyAddress)
                <div class="meta">{{ $companyAddress }}</div>
            @endif
        </div>
        <div class="header-title">
            <div class="doc-title">Resumen de Asistencia</div>
            <div class="doc-date">{{ $attendanceDay->date->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</div>
        </div>
    </div>

    {{-- Datos del empleado + estado en dos columnas --}}
    <div class="section">
        <div class="section-title">Empleado</div>
        <div class="two-col">
            <div class="col">
                <div class="data-grid">
                    <div class="data-row">
                        <div class="data-label">Nombre:</div>
                        <div class="data-value">{{ $attendanceDay->employee->full_name }}</div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">C.I.:</div>
                        <div class="data-value">{{ $attendanceDay->employee->ci }}</div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">Sucursal:</div>
                        <div class="data-value">{{ $attendanceDay->employee->branch?->name ?? 'N/A' }}</div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="data-grid">
                    <div class="data-row">
                        <div class="data-label">Cargo:</div>
                        <div class="data-value">{{ $attendanceDay->employee->activeContract?->position?->name ?? 'N/A' }}</div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">Departamento:</div>
                        <div class="data-value">{{ $attendanceDay->employee->activeContract?->position?->department?->name ?? 'N/A' }}</div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">Estado del día:</div>
                        <div class="data-value text-bold">{{ \App\Models\AttendanceDay::getStatusLabel($attendanceDay->status) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Métricas (solo si está presente) --}}
    @if ($attendanceDay->status === 'present')
        <div class="metrics-row">
            <div class="metric">
                <div class="metric-val">{{ number_format($attendanceDay->total_hours ?? 0, 2) }} hrs</div>
                <div class="metric-lbl">Horas totales</div>
            </div>
            <div class="metric">
                <div class="metric-val">{{ number_format($attendanceDay->net_hours ?? 0, 2) }} hrs</div>
                <div class="metric-lbl">Horas netas</div>
            </div>
            <div class="metric">
                <div class="metric-val">{{ $attendanceDay->late_minutes ?? 0 }} min</div>
                <div class="metric-lbl">Tardanza</div>
            </div>
            <div class="metric">
                <div class="metric-val">{{ number_format($attendanceDay->extra_hours ?? 0, 2) }} hrs</div>
                <div class="metric-lbl">Horas extra</div>
            </div>
            <div class="metric metric-last">
                <div class="metric-val">{{ $attendanceDay->overtime_approved ? 'Sí' : 'No' }}</div>
                <div class="metric-lbl">HE aprobadas</div>
            </div>
        </div>
    @endif

    {{-- Horarios --}}
    <div class="section">
        <div class="section-title">Horarios y asistencia</div>
        <table>
            <thead>
                <tr>
                    <th class="text-left" style="width:28%">Concepto</th>
                    <th style="width:24%">Esperado</th>
                    <th style="width:24%">Registrado</th>
                    <th style="width:24%">Diferencia</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-left text-bold">Entrada</td>
                    <td>{{ $attendanceDay->expected_check_in ?? '—' }}</td>
                    <td>{{ $attendanceDay->check_in_time ?? '—' }}</td>
                    <td>
                        @if ($attendanceDay->late_minutes > 0)
                            +{{ $attendanceDay->late_minutes }} min
                        @else
                            —
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="text-left text-bold">Salida</td>
                    <td>{{ $attendanceDay->expected_check_out ?? '—' }}</td>
                    <td>{{ $attendanceDay->check_out_time ?? '—' }}</td>
                    <td>
                        @if ($attendanceDay->early_leave_minutes > 0)
                            -{{ $attendanceDay->early_leave_minutes }} min
                        @else
                            —
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="text-left text-bold">Descanso</td>
                    <td>{{ $attendanceDay->expected_break_minutes ?? 0 }} min</td>
                    <td>{{ $attendanceDay->break_minutes ?? 0 }} min</td>
                    <td>
                        @php $breakDiff = ($attendanceDay->break_minutes ?? 0) - ($attendanceDay->expected_break_minutes ?? 0); @endphp
                        {{ $breakDiff > 0 ? '+' . $breakDiff . ' min' : ($breakDiff < 0 ? $breakDiff . ' min' : '—') }}
                    </td>
                </tr>
                @if ($attendanceDay->status === 'present')
                    <tr>
                        <td class="text-left text-bold">Total horas</td>
                        <td>{{ $attendanceDay->expected_hours ?? 0 }} hrs</td>
                        <td>{{ number_format($attendanceDay->total_hours ?? 0, 2) }} hrs</td>
                        <td>
                            @php $hoursDiff = ($attendanceDay->total_hours ?? 0) - ($attendanceDay->expected_hours ?? 0); @endphp
                            {{ $hoursDiff > 0 ? '+' . number_format($hoursDiff, 1) . ' hrs' : ($hoursDiff < 0 ? number_format($hoursDiff, 1) . ' hrs' : '—') }}
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    {{-- Eventos de marcación --}}
    @if ($attendanceDay->events->isNotEmpty())
        <div class="section">
            <div class="section-title">Eventos de marcación</div>
            <table>
                <thead>
                    <tr>
                        <th style="width:25%">Hora</th>
                        <th class="text-left">Tipo de evento</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($attendanceDay->events->sortBy('recorded_at') as $event)
                        <tr>
                            <td class="text-bold">{{ $event->recorded_at->format('H:i:s') }}</td>
                            <td class="text-left">{{ \App\Models\AttendanceEvent::getEventTypeLabel($event->event_type) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Notas --}}
    @if ($attendanceDay->notes)
        <div class="notes-box">
            <div class="notes-label">Observaciones:</div>
            {{ $attendanceDay->notes }}
        </div>
    @endif

    {{-- Firmas --}}
    <div class="signature-row">
        <div class="signature-cell">
            <div class="signature-line">Empleado</div>
            <div class="signature-sub">{{ $attendanceDay->employee->full_name }}</div>
            <div class="signature-sub">CI: {{ $attendanceDay->employee->ci }}</div>
        </div>
        <div class="signature-cell">
            <div class="signature-line">Recursos Humanos</div>
            <div class="signature-sub">Firma y sello</div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }}
        @if ($attendanceDay->is_calculated) &nbsp;·&nbsp; Calculado: {{ $attendanceDay->calculated_at?->format('d/m/Y H:i') }} @endif
        @if ($city) &nbsp;·&nbsp; {{ $city }}, Paraguay @endif
    </div>

</body>
</html>
