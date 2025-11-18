<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Resumen de Asistencia</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #333;
        }

        h1 {
            text-align: center;
            font-size: 14px;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        th,
        td {
            border: 1px solid #999;
            padding: 4px;
            text-align: left;
        }

        .label {
            font-weight: bold;
            background-color: #f2f2f2;
        }

        .section-title {
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 3px;
            font-size: 12px;
        }

        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 9px;
            color: #777;
        }
    </style>
</head>

<body>

    <h1>Resumen de Asistencia - {{ $attendanceDay->date->format('d/m/Y') }}</h1>

    <div class="section">
        <div class="section-title">Datos Generales</div>
        <table>
            <tr>
                <td class="label">Empleado</td>
                <td>{{ $attendanceDay->employee->full_name }}</td>
            </tr>
            <tr>
                <td class="label">CI</td>
                <td>{{ $attendanceDay->employee->ci }}</td>
            </tr>
            <tr>
                <td class="label">Sucursal</td>
                <td>{{ $attendanceDay->employee->branch->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Departamento</td>
                <td>{{ $attendanceDay->employee->position->department->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Posición</td>
                <td>{{ $attendanceDay->employee->position->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Estado</td>
                <td>{{ ucfirst($attendanceDay->status_in_spanish) }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Horarios</div>
        <table>
            <tr>
                <td class="label">Hora Entrada Esperada</td>
                <td>{{ $attendanceDay->expected_check_in ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Hora Salida Esperada</td>
                <td>{{ $attendanceDay->expected_check_out ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Min. de Descanso Esperados</td>
                <td>{{ $attendanceDay->expected_break_minutes ?? 0 }}</td>
            </tr>
        </table>
    </div>

    @if ($attendanceDay->status === 'present')
        <div class="section">
            <div class="section-title">Cálculos</div>
            <table>
                <tr>
                    <td class="label">Horas Esperadas</td>
                    <td>{{ $attendanceDay->expected_hours ?? 0 }}</td>
                </tr>
                <tr>
                    <td class="label">Horas Totales</td>
                    <td>{{ $attendanceDay->total_hours ?? 0 }}</td>
                </tr>
                <tr>
                    <td class="label">Horas Netas</td>
                    <td>{{ $attendanceDay->net_hours ?? 0 }}</td>
                </tr>
                <tr>
                    <td class="label">Horas Extra</td>
                    <td>{{ $attendanceDay->extra_hours ?? 0 }}</td>
                </tr>
                <tr>
                    <td class="label">Min. Llegada Tarde</td>
                    <td>{{ $attendanceDay->late_minutes ?? 0 }}</td>
                </tr>
                <tr>
                    <td class="label">Min. Salida Anticipada</td>
                    <td>{{ $attendanceDay->early_leave_minutes ?? 0 }}</td>
                </tr>
                <tr>
                    <td class="label">Min. de Descanso</td>
                    <td>{{ $attendanceDay->break_minutes ?? 0 }}</td>
                </tr>
            </table>
        </div>
    @endif

    <div class="section">
        <div class="section-title">Indicadores Especiales</div>
        <table>
            <tr>
                <td class="label">De vacaciones</td>
                <td>{{ $attendanceDay->on_vacation ? 'Sí' : 'No' }}</td>
            </tr>
            <tr>
                <td class="label">Ausencia justificada</td>
                <td>{{ $attendanceDay->justified_absence ? 'Sí' : 'No' }}</td>
            </tr>
            <tr>
                <td class="label">Feriado</td>
                <td>{{ $attendanceDay->is_holiday ? 'Sí' : 'No' }}</td>
            </tr>
            <tr>
                <td class="label">Fin de Semana</td>
                <td>{{ $attendanceDay->is_weekend ? 'Sí' : 'No' }}</td>
            </tr>
            <tr>
                <td class="label">Ajuste Manual</td>
                <td>{{ $attendanceDay->manual_adjustment ? 'Sí' : 'No' }}</td>
            </tr>
            <tr>
                <td class="label">Anomalía Detectada</td>
                <td>{{ $attendanceDay->anomaly_flag ? 'Sí' : 'No' }}</td>
            </tr>
            <tr>
                <td class="label">Horas Extra Aprobadas</td>
                <td>{{ $attendanceDay->overtime_approved ? 'Sí' : 'No' }}</td>
            </tr>
        </table>
    </div>

    @if ($attendanceDay->notes)
        <div class="section">
            <div class="section-title">Notas de RRHH</div>
            <table>
                <tr>
                    <td>{{ $attendanceDay->notes }}</td>
                </tr>
            </table>
        </div>
    @endif

    @if ($attendanceDay->events->isNotEmpty())
        <div class="section">
            <div class="section-title">Eventos de Asistencia</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 30%;">Hora</th>
                        <th>Tipo de Evento</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($attendanceDay->events->sortBy('recorded_at') as $event)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($event->recorded_at)->format('H:i:s') }}</td>
                            <td>
                                @switch($event->event_type)
                                    @case('check_in')
                                        Entrada
                                    @break

                                    @case('check_out')
                                        Salida
                                    @break

                                    @case('break_start')
                                        Inicio de descanso
                                    @break

                                    @case('break_end')
                                        Fin de descanso
                                    @break

                                    @default
                                        Otro
                                @endswitch
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <p class="footer">
        Documento generado automáticamente el {{ now()->format('d/m/Y H:i') }}
    </p>

</body>

</html>
