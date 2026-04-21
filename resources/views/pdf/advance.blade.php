<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Adelanto #{{ $advance->id }}</title>
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

        .highlight-box {
            margin: 15px 0;
            padding: 15px;
            border: 2px solid #000;
            text-align: center;
        }

        .highlight-label {
            font-size: 10px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .highlight-amount {
            font-size: 20px;
            font-weight: bold;
            font-family: 'Courier New', monospace;
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

        .info-box {
            margin: 15px 0;
            padding: 10px 12px;
            border: 1px solid #ccc;
            font-size: 9px;
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
    <div class="title">Comprobante de Adelanto de Salario</div>
    <div class="subtitle">Documento #{{ $advance->id }}</div>

    {{-- Monto destacado --}}
    <div class="highlight-box">
        <div class="highlight-label">Monto del Adelanto</div>
        <div class="highlight-amount">Gs. {{ number_format($advance->amount, 0, ',', '.') }}</div>
    </div>

    {{-- Informacion del Empleado --}}
    <div class="section">
        <div class="section-title">Informacion del Empleado</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nombre Completo:</div>
                <div class="info-value">{{ $advance->employee->full_name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Cedula de Identidad:</div>
                <div class="info-value">{{ $advance->employee->ci }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Cargo:</div>
                <div class="info-value">{{ $advance->employee->activeContract?->position?->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Departamento:</div>
                <div class="info-value">{{ $advance->employee->activeContract?->position?->department?->name ?? 'N/A' }}</div>
            </div>
        </div>
    </div>

    {{-- Detalles del Adelanto --}}
    <div class="section">
        <div class="section-title">Detalles del Adelanto</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Estado:</div>
                <div class="info-value">{{ \App\Models\Advance::getStatusLabel($advance->status) }}</div>
            </div>
            @if ($advance->approved_at)
                <div class="info-row">
                    <div class="info-label">Fecha de Aprobacion:</div>
                    <div class="info-value">{{ $advance->approved_at->format('d/m/Y') }}</div>
                </div>
            @endif
            @if ($advance->approvedBy)
                <div class="info-row">
                    <div class="info-label">Aprobado por:</div>
                    <div class="info-value">{{ $advance->approvedBy->name }}</div>
                </div>
            @endif
            @if ($advance->payroll)
                <div class="info-row">
                    <div class="info-label">Descontado en Nomina:</div>
                    <div class="info-value">{{ $advance->payroll->period?->name ?? 'N/A' }}</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Notas --}}
    @if ($advance->notes)
        <div class="note-section">
            <div class="note-title">Observaciones:</div>
            <p>{{ $advance->notes }}</p>
        </div>
    @endif

    {{-- Nota legal --}}
    <div class="info-box">
        El presente comprobante acredita el adelanto de salario solicitado por el empleado. El monto indicado
        sera descontado automaticamente en la liquidacion de nomina del periodo correspondiente.
    </div>

    {{-- Firmas --}}
    <div class="signature-section">
        <div class="signature-item">
            <div class="signature-line"></div>
            <div class="signature-label">Empleado</div>
            <div class="signature-sublabel">{{ $advance->employee->full_name }}</div>
            <div class="signature-sublabel">CI: {{ $advance->employee->ci }}</div>
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
        @if ($city)
            | {{ $city }}, Paraguay
        @endif
    </div>
</body>

</html>
