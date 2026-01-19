<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Vacaciones</title>
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
            line-height: 1.4;
            padding: 20mm 25mm;
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
            width: 30%;
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

        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .signature-section {
            margin-top: 60px;
            page-break-inside: avoid;
        }

        .signature-box {
            display: table;
            width: 100%;
            margin-top: 40px;
        }

        .signature-item {
            display: table-cell;
            text-align: center;
            padding: 0 20px;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin-bottom: 8px;
            padding-top: 2px;
        }

        .signature-label {
            font-size: 10px;
            font-weight: bold;
        }

        .signature-sublabel {
            font-size: 9px;
            color: #666;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .reason-box {
            border: 1px solid #ddd;
            padding: 12px;
            background-color: #f9f9f9;
            min-height: 60px;
            border-radius: 4px;
        }

        .important-note {
            background-color: #fff3cd;
            border-left: 3px solid #ffc107;
            padding: 12px;
            margin: 20px 0;
            font-size: 10px;
        }
    </style>
</head>

<body>
    {{-- Header --}}
    <div class="header">
        <h1>SOLICITUD DE VACACIONES</h1>
        <p>Documento de Autorización y Registro</p>
    </div>

    {{-- Información del Empleado --}}
    <div class="section">
        <div class="section-title">INFORMACIÓN DEL EMPLEADO</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nombre Completo:</div>
                <div class="info-value">{{ $vacation->employee->first_name }} {{ $vacation->employee->last_name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Cédula de Identidad:</div>
                <div class="info-value">{{ $vacation->employee->ci }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Cargo:</div>
                <div class="info-value">{{ $vacation->employee->position->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Departamento:</div>
                <div class="info-value">{{ $vacation->employee->position->department->name ?? 'N/A' }}</div>
            </div>
        </div>
    </div>

    {{-- Período de Vacaciones --}}
    <div class="section">
        <div class="section-title">PERÍODO DE VACACIONES</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Fecha de Inicio:</div>
                <div class="info-value">{{ $vacation->start_date->format('d/m/Y') }}
                    ({{ ucfirst($vacation->start_date->locale('es')->dayName) }})</div>
            </div>
            <div class="info-row">
                <div class="info-label">Fecha de Fin:</div>
                <div class="info-value">{{ $vacation->end_date->format('d/m/Y') }}
                    ({{ ucfirst($vacation->end_date->locale('es')->dayName) }})</div>
            </div>
            <div class="info-row">
                <div class="info-label">Total de Días:</div>
                <div class="info-value">
                    <span class="badge badge-info">{{ $vacation->days_requested }} días</span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Tipo de Vacaciones:</div>
                <div class="info-value">
                    <span class="badge {{ $vacation->type === 'paid' ? 'badge-success' : 'badge-warning' }}">
                        {{ $vacation->type === 'paid' ? 'Remuneradas' : 'No Remuneradas' }}
                    </span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Estado:</div>
                <div class="info-value">
                    <span
                        class="badge
                        @if ($vacation->status === 'approved') badge-success
                        @elseif($vacation->status === 'rejected') badge-danger
                        @else badge-warning @endif">
                        {{ $vacation->status === 'approved' ? 'Aprobado' : ($vacation->status === 'rejected' ? 'Rechazado' : 'Pendiente') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Motivo --}}
    @if ($vacation->reason)
        <div class="section">
            <div class="section-title">MOTIVO DE LA SOLICITUD</div>
            <div class="reason-box">
                {{ $vacation->reason }}
            </div>
        </div>
    @endif

    {{-- Nota Importante --}}
    <div class="important-note">
        <strong>NOTA IMPORTANTE:</strong> El empleado deberá reintegrarse a sus labores el día
        <strong>{{ $vacation->end_date->addDay()->format('d/m/Y') }}</strong>.
        En caso de no presentarse sin justificación, se considerará como ausencia injustificada.
    </div>

    {{-- Firmas --}}
    <div class="signature-section">
        <div class="section-title">FIRMAS Y AUTORIZACIONES</div>
        <div class="signature-box">
            <div class="signature-item">
                <div class="signature-line"></div>
                <div class="signature-label">Empleado</div>
                <div class="signature-sublabel">{{ $vacation->employee->first_name }}
                    {{ $vacation->employee->last_name }}</div>
                <div class="signature-sublabel">CI: {{ $vacation->employee->ci }}</div>
            </div>
            <div class="signature-item">
                <div class="signature-line"></div>
                <div class="signature-label">Supervisor/Jefe Inmediato</div>
                <div class="signature-sublabel">Nombre y Firma</div>
            </div>
            <div class="signature-item">
                <div class="signature-line"></div>
                <div class="signature-label">Recursos Humanos</div>
                <div class="signature-sublabel">Nombre y Firma</div>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        Documento generado el {{ now()->format('d/m/Y H:i') }} | Solicitud #{{ $vacation->id }}
        @if ($vacation->status === 'approved')
            | Aprobado el {{ $vacation->updated_at->format('d/m/Y H:i') }}
        @endif
    </div>
</body>

</html>
