<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Resumen de Asistencia</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #2c3e50;
            line-height: 1.5;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #3498db;
        }

        .header h1 {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .header .subtitle {
            font-size: 11px;
            color: #7f8c8d;
        }

        .info-box {
            background-color: #ecf0f1;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
        }

        .status-present {
            background-color: #d4edda;
            color: #155724;
        }

        .status-absent {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-on_leave {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-holiday {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-weekend {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .section {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }

        .section-title {
            font-weight: bold;
            font-size: 12px;
            color: #2c3e50;
            background-color: #3498db;
            color: white;
            padding: 6px 10px;
            margin-bottom: 8px;
            border-radius: 3px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        th {
            background-color: #34495e;
            color: white;
            padding: 6px 8px;
            text-align: left;
            font-size: 10px;
            font-weight: bold;
        }

        td {
            border: 1px solid #bdc3c7;
            padding: 5px 8px;
            font-size: 10px;
        }

        tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .label-cell {
            font-weight: bold;
            background-color: #ecf0f1;
            width: 40%;
            color: #2c3e50;
        }

        .value-cell {
            background-color: white;
        }

        .highlight-positive {
            color: #27ae60;
            font-weight: bold;
        }

        .highlight-negative {
            color: #e74c3c;
            font-weight: bold;
        }

        .highlight-warning {
            color: #f39c12;
            font-weight: bold;
        }

        .metric-grid {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }

        .metric-item {
            display: table-cell;
            width: 33.33%;
            padding: 8px;
            text-align: center;
            border: 1px solid #ddd;
        }

        .metric-value {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
        }

        .metric-label {
            font-size: 9px;
            color: #7f8c8d;
            text-transform: uppercase;
        }

        .notes-box {
            background-color: #fff9e6;
            border-left: 4px solid #f39c12;
            padding: 10px;
            margin-top: 10px;
            font-style: italic;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #ecf0f1;
            font-size: 9px;
            color: #95a5a6;
        }

        .check-icon {
            color: #27ae60;
        }

        .cross-icon {
            color: #e74c3c;
        }

        .events-table td {
            text-align: center;
        }

        .events-table td:first-child {
            font-weight: bold;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
        }

        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
    </style>
</head>

<body>
    {{-- Header --}}
    <div class="header">
        <h1>Resumen de Asistencia</h1>
        <div class="subtitle">{{ $attendanceDay->date->format('l, d \d\e F \d\e Y') }}</div>
    </div>

    {{-- Información General --}}
    <div class="section">
        <div class="section-title">INFORMACIÓN GENERAL</div>
        <table>
            <tr>
                <td class="label-cell">Empleado</td>
                <td class="value-cell">{{ $attendanceDay->employee->full_name }}</td>
            </tr>
            <tr>
                <td class="label-cell">Cédula de Identidad</td>
                <td class="value-cell">{{ number_format($attendanceDay->employee->ci, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label-cell">Sucursal</td>
                <td class="value-cell">{{ $attendanceDay->employee->branch->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Departamento</td>
                <td class="value-cell">{{ $attendanceDay->employee->position->department->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Cargo</td>
                <td class="value-cell">{{ $attendanceDay->employee->position->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Estado</td>
                <td class="value-cell">
                    <span class="status-badge status-{{ $attendanceDay->status }}">
                        {{ ucfirst($attendanceDay->status_in_spanish) }}
                    </span>
                </td>
            </tr>
            @if ($attendanceDay->is_calculated)
                <tr>
                    <td class="label-cell">Último Cálculo</td>
                    <td class="value-cell">{{ $attendanceDay->calculated_at?->format('d/m/Y H:i') ?? 'No calculado' }}
                    </td>
                </tr>
            @endif
        </table>
    </div>

    {{-- Horarios Programados --}}
    <div class="section">
        <div class="section-title">HORARIOS PROGRAMADOS</div>
        <table>
            <tr>
                <td class="label-cell">Entrada Esperada</td>
                <td class="value-cell">{{ $attendanceDay->expected_check_in ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Salida Esperada</td>
                <td class="value-cell">{{ $attendanceDay->expected_check_out ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Horas Esperadas</td>
                <td class="value-cell">{{ $attendanceDay->expected_hours ?? 0 }} hrs</td>
            </tr>
            <tr>
                <td class="label-cell">Descanso Esperado</td>
                <td class="value-cell">{{ $attendanceDay->expected_break_minutes ?? 0 }} min</td>
            </tr>
        </table>
    </div>

    {{-- Métricas de Asistencia (solo si está presente) --}}
    @if ($attendanceDay->status === 'present')
        <div class="section">
            <div class="section-title">RESUMEN DE ASISTENCIA</div>

            {{-- Horas Trabajadas --}}
            <table>
                <tr>
                    <td class="label-cell">Entrada Registrada</td>
                    <td
                        class="value-cell 
                        @if ($attendanceDay->late_minutes > 0) highlight-negative 
                        @else highlight-positive @endif">
                        {{ $attendanceDay->check_in_time ?? '—' }}
                        @if ($attendanceDay->late_minutes > 0)
                            ({{ $attendanceDay->late_minutes }} min tarde)
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="label-cell">Salida Registrada</td>
                    <td
                        class="value-cell 
                        @if ($attendanceDay->early_leave_minutes > 0) highlight-warning 
                        @else highlight-positive @endif">
                        {{ $attendanceDay->check_out_time ?? '—' }}
                        @if ($attendanceDay->early_leave_minutes > 0)
                            ({{ $attendanceDay->early_leave_minutes }} min antes)
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="label-cell">Horas Totales Trabajadas</td>
                    <td class="value-cell"><strong>{{ $attendanceDay->total_hours ?? 0 }} hrs</strong></td>
                </tr>
                <tr>
                    <td class="label-cell">Horas Netas (sin descansos)</td>
                    <td class="value-cell highlight-positive"><strong>{{ $attendanceDay->net_hours ?? 0 }} hrs</strong>
                    </td>
                </tr>
                <tr>
                    <td class="label-cell">Descansos Tomados</td>
                    <td
                        class="value-cell 
                        @if (($attendanceDay->break_minutes ?? 0) > ($attendanceDay->expected_break_minutes ?? 0)) highlight-warning @endif">
                        {{ $attendanceDay->break_minutes ?? 0 }} min
                    </td>
                </tr>
                @if ($attendanceDay->extra_hours > 0)
                    <tr>
                        <td class="label-cell">Horas Extra</td>
                        <td class="value-cell highlight-warning">
                            <strong>{{ $attendanceDay->extra_hours }} hrs</strong>
                            @if ($attendanceDay->overtime_approved)
                                <span class="badge badge-success">Aprobadas</span>
                            @else
                                <span class="badge badge-warning">Pendientes</span>
                            @endif
                        </td>
                    </tr>
                @endif
            </table>
        </div>
    @endif

    {{-- Indicadores Especiales --}}
    <div class="section">
        <div class="section-title">CONDICIONES ESPECIALES</div>
        <table>
            <tr>
                <td class="label-cell">De vacaciones</td>
                <td class="value-cell">
                    @if ($attendanceDay->on_vacation)
                        <span class="check-icon">✓</span> Sí
                    @else
                        <span class="cross-icon">✗</span> No
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label-cell">Ausencia justificada</td>
                <td class="value-cell">
                    @if ($attendanceDay->justified_absence)
                        <span class="check-icon">✓</span> Sí
                    @else
                        <span class="cross-icon">✗</span> No
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label-cell">Día feriado</td>
                <td class="value-cell">
                    @if ($attendanceDay->is_holiday)
                        <span class="check-icon">✓</span> Sí
                    @else
                        <span class="cross-icon">✗</span> No
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label-cell">Fin de semana</td>
                <td class="value-cell">
                    @if ($attendanceDay->is_weekend)
                        <span class="check-icon">✓</span> Sí
                    @else
                        <span class="cross-icon">✗</span> No
                    @endif
                </td>
            </tr>
            @if ($attendanceDay->is_extraordinary_work)
                <tr>
                    <td class="label-cell">Trabajo extraordinario</td>
                    <td class="value-cell highlight-warning">
                        <span class="check-icon">✓</span> Sí (Feriado/Fin de semana)
                    </td>
                </tr>
            @endif
            <tr>
                <td class="label-cell">Ajuste manual</td>
                <td class="value-cell">
                    @if ($attendanceDay->manual_adjustment)
                        <span class="badge badge-info">Sí</span>
                    @else
                        <span class="cross-icon">✗</span> No
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label-cell">Anomalía detectada</td>
                <td class="value-cell">
                    @if ($attendanceDay->anomaly_flag)
                        <span class="badge badge-danger">Sí</span>
                    @else
                        <span class="check-icon">✓</span> No
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- Eventos de Marcación --}}
    @if ($attendanceDay->events->isNotEmpty())
        <div class="section">
            <div class="section-title">EVENTOS DE MARCACIÓN</div>
            <table class="events-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Hora</th>
                        <th style="width: 50%;">Tipo de Evento</th>
                        <th style="width: 25%;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($attendanceDay->events->sortBy('recorded_at') as $event)
                        <tr>
                            <td><strong>{{ \Carbon\Carbon::parse($event->recorded_at)->format('H:i:s') }}</strong></td>
                            <td>
                                @switch($event->event_type)
                                    @case('check_in')
                                        → Entrada jornada
                                    @break

                                    @case('check_out')
                                        ← Salida jornada
                                    @break

                                    @case('break_start')
                                        ⏸ Inicio de descanso
                                    @break

                                    @case('break_end')
                                        ▶ Fin de descanso
                                    @break

                                    @default
                                        ? Otro
                                @endswitch
                            </td>
                            <td>
                                @switch($event->event_type)
                                    @case('check_in')
                                        <span class="badge badge-success">Entrada</span>
                                    @break

                                    @case('check_out')
                                        <span class="badge badge-danger">Salida</span>
                                    @break

                                    @case('break_start')
                                        <span class="badge badge-warning">Descanso</span>
                                    @break

                                    @case('break_end')
                                        <span class="badge badge-info">Retorno</span>
                                    @break
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
            <div class="section-title">NOTAS Y OBSERVACIONES</div>
            <div class="notes-box">
                {{ $attendanceDay->notes }}
            </div>
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        <p>Documento generado automáticamente el {{ now()->format('d/m/Y') }} a las {{ now()->format('H:i') }}</p>
        <p>Sistema de Gestión de Recursos Humanos</p>
    </div>
</body>

</html>
