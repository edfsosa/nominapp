<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen de Asistencia</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            padding: 40px;
            font-size: 11px;
            line-height: 1.4;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }

        .header h1 {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 10px;
            color: #666;
        }

        .section {
            margin-bottom: 20px;
        }

        .section-title {
            font-weight: bold;
            font-size: 12px;
            background-color: #f0f0f0;
            padding: 8px;
            margin-bottom: 10px;
            border-left: 3px solid #333;
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
            width: 40%;
            padding: 8px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }

        .info-value {
            display: table-cell;
            padding: 8px;
            border: 1px solid #ddd;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 10px;
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

        .badge-gray {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .highlight-positive {
            color: #155724;
            font-weight: bold;
        }

        .highlight-negative {
            color: #721c24;
            font-weight: bold;
        }

        .highlight-warning {
            color: #856404;
            font-weight: bold;
        }

        .metrics-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }

        .metric-item {
            display: table-cell;
            width: 33.33%;
            padding: 12px;
            text-align: center;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }

        .metric-value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }

        .metric-label {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .events-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .events-table th {
            background-color: #f0f0f0;
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 10px;
        }

        .events-table td {
            padding: 6px 8px;
            border: 1px solid #ddd;
            font-size: 10px;
        }

        .events-table td:first-child {
            font-weight: bold;
            text-align: center;
        }

        .notes-box {
            border: 1px solid #ddd;
            padding: 12px;
            background-color: #fff9e6;
            border-radius: 4px;
            border-left: 3px solid #ffc107;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .check-icon {
            color: #155724;
            font-weight: bold;
        }

        .cross-icon {
            color: #721c24;
            font-weight: bold;
        }
    </style>
</head>

<body>
    {{-- Header --}}
    <div class="header">
        <h1>RESUMEN DE ASISTENCIA</h1>
        <p>Registro Diario de Control de Asistencia</p>
        <p style="margin-top: 5px;">
            <strong>{{ $attendanceDay->date->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</strong></p>
    </div>

    {{-- Información del Empleado --}}
    <div class="section">
        <div class="section-title">INFORMACIÓN DEL EMPLEADO</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nombre Completo:</div>
                <div class="info-value">{{ $attendanceDay->employee->full_name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Cédula de Identidad:</div>
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
                <div class="info-label">Sucursal:</div>
                <div class="info-value">{{ $attendanceDay->employee->branch->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Estado de Asistencia:</div>
                <div class="info-value">
                    @php
                        $statusConfig = [
                            'present' => ['label' => 'Presente', 'class' => 'badge-success'],
                            'absent' => ['label' => 'Ausente', 'class' => 'badge-danger'],
                            'on_leave' => ['label' => 'De Permiso', 'class' => 'badge-warning'],
                            'holiday' => ['label' => 'Feriado', 'class' => 'badge-info'],
                            'weekend' => ['label' => 'Fin de Semana', 'class' => 'badge-gray'],
                        ];
                        $config = $statusConfig[$attendanceDay->status] ?? [
                            'label' => $attendanceDay->status,
                            'class' => 'badge-gray',
                        ];
                    @endphp
                    <span class="badge {{ $config['class'] }}">{{ $config['label'] }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Métricas Principales --}}
    @if ($attendanceDay->status === 'present')
        <div class="section">
            <div class="section-title">MÉTRICAS DE ASISTENCIA</div>
            <div class="metrics-grid">
                <div class="metric-item">
                    <div class="metric-value highlight-positive">{{ $attendanceDay->total_hours ?? 0 }} hrs</div>
                    <div class="metric-label">Horas Totales</div>
                </div>
                <div class="metric-item">
                    <div class="metric-value">{{ $attendanceDay->net_hours ?? 0 }} hrs</div>
                    <div class="metric-label">Horas Netas</div>
                </div>
                <div class="metric-item">
                    <div class="metric-value {{ $attendanceDay->extra_hours > 0 ? 'highlight-warning' : '' }}">
                        {{ $attendanceDay->extra_hours ?? 0 }} hrs
                    </div>
                    <div class="metric-label">Horas Extra</div>
                </div>
            </div>
        </div>
    @endif

    {{-- Horarios Programados --}}
    <div class="section">
        <div class="section-title">HORARIOS PROGRAMADOS</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Entrada Esperada:</div>
                <div class="info-value">{{ $attendanceDay->expected_check_in ?? '—' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Salida Esperada:</div>
                <div class="info-value">{{ $attendanceDay->expected_check_out ?? '—' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Horas Esperadas:</div>
                <div class="info-value">{{ $attendanceDay->expected_hours ?? 0 }} hrs</div>
            </div>
            <div class="info-row">
                <div class="info-label">Descanso Esperado:</div>
                <div class="info-value">{{ $attendanceDay->expected_break_minutes ?? 0 }} min</div>
            </div>
        </div>
    </div>

    {{-- Asistencia Registrada --}}
    @if ($attendanceDay->status === 'present')
        <div class="section">
            <div class="section-title">ASISTENCIA REGISTRADA</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Entrada Registrada:</div>
                    <div
                        class="info-value {{ $attendanceDay->late_minutes > 0 ? 'highlight-negative' : 'highlight-positive' }}">
                        {{ $attendanceDay->check_in_time ?? '—' }}
                        @if ($attendanceDay->late_minutes > 0)
                            <span class="badge badge-danger">{{ $attendanceDay->late_minutes }} min tarde</span>
                        @endif
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Salida Registrada:</div>
                    <div
                        class="info-value {{ $attendanceDay->early_leave_minutes > 0 ? 'highlight-warning' : 'highlight-positive' }}">
                        {{ $attendanceDay->check_out_time ?? '—' }}
                        @if ($attendanceDay->early_leave_minutes > 0)
                            <span class="badge badge-warning">{{ $attendanceDay->early_leave_minutes }} min
                                antes</span>
                        @endif
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Horas Trabajadas:</div>
                    <div class="info-value"><strong>{{ $attendanceDay->total_hours ?? 0 }} hrs</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Horas Netas (sin descansos):</div>
                    <div class="info-value highlight-positive"><strong>{{ $attendanceDay->net_hours ?? 0 }}
                            hrs</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Descansos Tomados:</div>
                    <div
                        class="info-value {{ ($attendanceDay->break_minutes ?? 0) > ($attendanceDay->expected_break_minutes ?? 0) ? 'highlight-warning' : '' }}">
                        {{ $attendanceDay->break_minutes ?? 0 }} min
                    </div>
                </div>
                @if ($attendanceDay->extra_hours > 0)
                    <div class="info-row">
                        <div class="info-label">Horas Extra:</div>
                        <div class="info-value highlight-warning">
                            <strong>{{ $attendanceDay->extra_hours }} hrs</strong>
                            @if ($attendanceDay->overtime_approved)
                                <span class="badge badge-success">Aprobadas</span>
                            @else
                                <span class="badge badge-warning">Pendientes</span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Condiciones Especiales --}}
    <div class="section">
        <div class="section-title">CONDICIONES ESPECIALES</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">De Vacaciones:</div>
                <div class="info-value">
                    @if ($attendanceDay->on_vacation)
                        <span class="check-icon">✓</span> Sí
                    @else
                        <span class="cross-icon">✗</span> No
                    @endif
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Ausencia Justificada:</div>
                <div class="info-value">
                    @if ($attendanceDay->justified_absence)
                        <span class="check-icon">✓</span> Sí
                    @else
                        <span class="cross-icon">✗</span> No
                    @endif
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Día Feriado:</div>
                <div class="info-value">
                    @if ($attendanceDay->is_holiday)
                        <span class="check-icon">✓</span> Sí
                    @else
                        <span class="cross-icon">✗</span> No
                    @endif
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Fin de Semana:</div>
                <div class="info-value">
                    @if ($attendanceDay->is_weekend)
                        <span class="check-icon">✓</span> Sí
                    @else
                        <span class="cross-icon">✗</span> No
                    @endif
                </div>
            </div>
            @if ($attendanceDay->is_extraordinary_work)
                <div class="info-row">
                    <div class="info-label">Trabajo Extraordinario:</div>
                    <div class="info-value highlight-warning">
                        <span class="check-icon">✓</span> Sí (Feriado/Fin de semana)
                    </div>
                </div>
            @endif
            <div class="info-row">
                <div class="info-label">Ajuste Manual:</div>
                <div class="info-value">
                    @if ($attendanceDay->manual_adjustment)
                        <span class="badge badge-info">Sí</span>
                    @else
                        <span class="cross-icon">✗</span> No
                    @endif
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Anomalía Detectada:</div>
                <div class="info-value">
                    @if ($attendanceDay->anomaly_flag)
                        <span class="badge badge-danger">Sí</span>
                    @else
                        <span class="check-icon">✓</span> No
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Eventos de Marcación --}}
    @if ($attendanceDay->events->isNotEmpty())
        <div class="section">
            <div class="section-title">EVENTOS DE MARCACIÓN</div>
            <table class="events-table">
                <thead>
                    <tr>
                        <th style="width: 20%;">Hora</th>
                        <th style="width: 50%;">Tipo de Evento</th>
                        <th style="width: 30%;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($attendanceDay->events->sortBy('recorded_at') as $event)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($event->recorded_at)->format('H:i:s') }}</td>
                            <td>
                                @switch($event->event_type)
                                    @case('check_in')
                                        → Entrada a la jornada
                                    @break

                                    @case('check_out')
                                        ← Salida de la jornada
                                    @break

                                    @case('break_start')
                                        ⏸ Inicio de descanso
                                    @break

                                    @case('break_end')
                                        ▶ Fin de descanso
                                    @break

                                    @default
                                        Otro evento
                                @endswitch
                            </td>
                            <td style="text-align: center;">
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
        Documento generado el {{ now()->format('d/m/Y H:i') }} | Sistema de Gestión de Recursos Humanos
        @if ($attendanceDay->is_calculated)
            | Calculado el {{ $attendanceDay->calculated_at?->format('d/m/Y H:i') }}
        @endif
    </div>
</body>

</html>
