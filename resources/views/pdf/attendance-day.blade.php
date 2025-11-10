<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Resumen de Asistencia</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #333;
        }

        h1 {
            text-align: center;
            font-size: 20px;
            margin-bottom: 20px;
        }

        .section {
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        td,
        th {
            border: 1px solid #999;
            padding: 8px;
            text-align: left;
        }

        .label {
            font-weight: bold;
        }
    </style>
</head>

<body>
    <h1>Resumen de Asistencia</h1>

    <div class="section">
        <table>
            <tr>
                <td class="label">Empleado</td>
                <td>{{ $attendanceDay->employee->first_name }} {{ $attendanceDay->employee->last_name }}</td>
            </tr>
            <tr>
                <td class="label">Fecha</td>
                <td>{{ $attendanceDay->date_formatted }}</td>
            </tr>
            <tr>
                <td class="label">Estado</td>
                <td>{{ ucfirst($attendanceDay->status_in_spanish) }}</td>
            </tr>
            <tr>
                <td class="label">Check-In</td>
                <td>{{ $attendanceDay->check_in_time ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Check-Out</td>
                <td>{{ $attendanceDay->check_out_time ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Minutos de descanso</td>
                <td>{{ $attendanceDay->break_minutes ?? 0 }}</td>
            </tr>
            <tr>
                <td class="label">Horas trabajadas</td>
                <td>{{ $attendanceDay->total_hours ?? 0 }}</td>
            </tr>
            @if ($attendanceDay->extra_hours)
                <tr>
                    <td class="label">Horas extra</td>
                    <td>{{ $attendanceDay->extra_hours }}</td>
                </tr>
            @endif
            @if ($attendanceDay->notes)
                <tr>
                    <td class="label">Notas</td>
                    <td>{{ $attendanceDay->notes }}</td>
                </tr>
            @endif
        </table>
    </div>

    <p style="text-align: center; margin-top: 40px;">
        Documento generado automáticamente el {{ now()->format('d/m/Y H:i') }}
    </p>
</body>

</html>
