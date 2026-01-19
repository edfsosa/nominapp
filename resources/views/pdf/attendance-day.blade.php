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
            font-size: 10px;
            line-height: 1.3;
            color: #000;
            padding: 20mm 25mm;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #000;
            padding-bottom: 8px;
        }

        .header h1 {
            font-size: 14px;
            margin-bottom: 3px;
            font-weight: bold;
        }

        .header p {
            font-size: 9px;
            margin-top: 2px;
        }

        .section {
            margin-bottom: 12px;
        }

        .section-title {
            font-weight: bold;
            font-size: 10px;
            padding: 4px 0;
            margin-bottom: 6px;
            border-bottom: 1px solid #000;
            text-transform: uppercase;
        }

        .info-row {
            display: flex;
            padding: 3px 0;
            border-bottom: 1px solid #ddd;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: bold;
            width: 35%;
            padding-right: 8px;
        }

        .info-value {
            width: 65%;
        }

        .metrics-grid {
            display: table;
            width: 100%;
            border: 1px solid #000;
            border-collapse: collapse;
        }

        .metric-item {
            display: table-cell;
            width: 33.33%;
            padding: 6px;
            text-align: center;
            border-right: 1px solid #ddd;
        }

        .metric-item:last-child {
            border-right: none;
        }

        .metric-value {
            font-size: 14px;
            font-weight: bold;
        }

        .metric-label {
            font-size: 8px;
            margin-top: 2px;
            text-transform: uppercase;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }

        .table th {
            background-color: #f5f5f5;
            padding: 4px 6px;
            border: 1px solid #000;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
        }

        .table td {
            padding: 3px 6px;
            border: 1px solid #ddd;
            font-size: 9px;
        }

        .notes-box {
            border: 1px solid #000;
            padding: 6px;
            font-size: 9px;
        }

        .footer {
            margin-top: 15px;
            text-align: center;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 6px;
        }

        .text-center {
            text-align: center;
        }

        .text-bold {
            font-weight: bold;
        }
    </style>
</head>

<body>
    {{-- Header --}}
    <div class="header">
        <h1>RESUMEN DE ASISTENCIA DIARIO</h1>
        <p>{{ $attendanceDay->date->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</p>
    </div>

    {{-- Información del Empleado --}}
    <div class="section">
        <div class="section-title">Información del Empleado</div>
        <div class="info-row">
            <div class="info-label">Empleado:</div>
            <div class="info-value">{{ $attendanceDay->employee->full_name }} (CI: {{ $attendanceDay->employee->ci }})
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Cargo/Dpto:</div>
            <div class="info-value">{{ $attendanceDay->employee->position->name ?? 'N/A' }} -
                {{ $attendanceDay->employee->position->department->name ?? 'N/A' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Estado:</div>
            <div class="info-value text-bold">
                @php
                    $statusLabels = [
                        'present' => 'Presente',
                        'absent' => 'Ausente',
                        'on_leave' => 'De Permiso',
                        'holiday' => 'Feriado',
                        'weekend' => 'Fin de Semana',
                    ];
                @endphp
                {{ $statusLabels[$attendanceDay->status] ?? $attendanceDay->status }}
            </div>
        </div>
    </div>

    {{-- Métricas Principales --}}
    @if ($attendanceDay->status === 'present')
        <div class="section">
            <div class="section-title">Resumen de Horas</div>
            <div class="metrics-grid">
                <div class="metric-item">
                    <div class="metric-value">{{ $attendanceDay->total_hours ?? 0 }} hrs</div>
                    <div class="metric-label">Totales</div>
                </div>
                <div class="metric-item">
                    <div class="metric-value">{{ $attendanceDay->net_hours ?? 0 }} hrs</div>
                    <div class="metric-label">Netas</div>
                </div>
                <div class="metric-item">
                    <div class="metric-value">{{ $attendanceDay->extra_hours ?? 0 }} hrs</div>
                    <div class="metric-label">Extra</div>
                </div>
            </div>
        </div>
    @endif

    {{-- Tabla Combinada: Horarios Esperados vs Registrados --}}
    <div class="section">
        <div class="section-title">Horarios y Asistencia</div>
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 30%;">Concepto</th>
                    <th style="width: 25%;">Esperado</th>
                    <th style="width: 25%;">Registrado</th>
                    <th style="width: 20%;">Diferencia</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-bold">Entrada</td>
                    <td>{{ $attendanceDay->expected_check_in ?? '—' }}</td>
                    <td>{{ $attendanceDay->check_in_time ?? '—' }}</td>
                    <td>
                        @if ($attendanceDay->late_minutes > 0)
                            +{{ $attendanceDay->late_minutes }} min
                        @elseif($attendanceDay->check_in_time)
                            —
                        @else
                            —
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="text-bold">Salida</td>
                    <td>{{ $attendanceDay->expected_check_out ?? '—' }}</td>
                    <td>{{ $attendanceDay->check_out_time ?? '—' }}</td>
                    <td>
                        @if ($attendanceDay->early_leave_minutes > 0)
                            -{{ $attendanceDay->early_leave_minutes }} min
                        @elseif($attendanceDay->check_out_time)
                            —
                        @else
                            —
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="text-bold">Descanso</td>
                    <td>{{ $attendanceDay->expected_break_minutes ?? 0 }} min</td>
                    <td>{{ $attendanceDay->break_minutes ?? 0 }} min</td>
                    <td>
                        @php
                            $breakDiff =
                                ($attendanceDay->break_minutes ?? 0) - ($attendanceDay->expected_break_minutes ?? 0);
                        @endphp
                        @if ($breakDiff > 0)
                            +{{ $breakDiff }} min
                        @elseif($breakDiff < 0)
                            {{ $breakDiff }} min
                        @else
                            —
                        @endif
                    </td>
                </tr>
                @if ($attendanceDay->status === 'present')
                    <tr>
                        <td class="text-bold">Total Horas</td>
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
                                —
                            @endif
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    {{-- Eventos de Marcación --}}
    @if ($attendanceDay->events->isNotEmpty())
        <div class="section">
            <div class="section-title">Eventos de Marcación</div>
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 20%;">Hora</th>
                        <th style="width: 80%;">Tipo de Evento</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($attendanceDay->events->sortBy('recorded_at') as $event)
                        <tr>
                            <td class="text-center text-bold">
                                {{ \Carbon\Carbon::parse($event->recorded_at)->format('H:i:s') }}</td>
                            <td>
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
        <div class="section">
            <div class="section-title">Notas</div>
            <div class="notes-box">{{ $attendanceDay->notes }}</div>
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        Generado: {{ now()->format('d/m/Y H:i') }}
        @if ($attendanceDay->is_calculated)
            | Calculado: {{ $attendanceDay->calculated_at?->format('d/m/Y H:i') }}
        @endif
    </div>
</body>

</html>
