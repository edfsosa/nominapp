<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
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
            font-size: 9px;
            line-height: 1.4;
        }

        .copy-label {
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: right;
            color: #555;
            margin-bottom: 4px;
            letter-spacing: 1px;
        }

        .company-header {
            text-align: center;
            margin-bottom: 6px;
            padding-bottom: 6px;
            border-bottom: 1px solid #000;
        }

        .company-logo {
            max-height: 28px;
            max-width: 90px;
            margin-bottom: 3px;
        }

        .company-name {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .company-info {
            font-size: 8px;
        }

        .title {
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 6px 0 2px 0;
        }

        .subtitle {
            text-align: center;
            font-size: 8px;
            margin-bottom: 6px;
        }

        .highlight-box {
            margin: 6px 0;
            padding: 6px;
            border: 2px solid #000;
            text-align: center;
        }

        .highlight-label {
            font-size: 8px;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .highlight-amount {
            font-size: 16px;
            font-weight: bold;
            font-family: 'Courier New', monospace;
        }

        .section {
            margin-bottom: 6px;
        }

        .section-title {
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
            padding: 3px 0;
            margin-bottom: 4px;
            border-bottom: 1px solid #000;
        }

        table.info-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.info-table th {
            text-align: left;
            font-weight: bold;
            width: 150px;
            padding: 3px 6px;
            border: 1px solid #000;
        }

        table.info-table td {
            padding: 3px 6px;
            border: 1px solid #000;
        }

        .note-section {
            margin: 5px 0;
            padding: 5px 8px;
            border: 1px solid #000;
        }

        .note-title {
            font-weight: bold;
            margin-bottom: 2px;
        }

        .info-box {
            margin: 5px 0;
            padding: 5px 8px;
            border: 1px solid #ccc;
            font-size: 8px;
        }

        table.signature-table {
            width: 100%;
            margin-top: 30px;
        }

        table.signature-table td {
            width: 50%;
            text-align: center;
            padding: 0 25px;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-bottom: 5px;
            padding-top: 40px;
        }

        .signature-label {
            font-size: 8px;
            font-weight: bold;
        }

        .signature-sublabel {
            font-size: 7px;
        }

        .footer {
            margin-top: 8px;
            text-align: center;
            font-size: 7px;
            border-top: 1px solid #ccc;
            padding-top: 4px;
        }

        .cut-line {
            border-top: 1px dashed #555;
            margin-top: 6mm;
        }
    </style>
</head>

<body>
    {{-- Envolvente único: impide que DomPDF inserte saltos de página adentro --}}
    <div style="page-break-inside: avoid;">

        @foreach (['COPIA EMPLEADO', 'COPIA EMPRESA'] as $copyLabel)
            <div style="padding: 10mm 14mm 6mm 14mm;">

                {{-- Etiqueta de copia --}}
                <div class="copy-label">{{ $copyLabel }}</div>

                {{-- Encabezado de la empresa --}}
                <div class="company-header">
                    @if ($companyLogo)
                        <img src="{{ $companyLogo }}" alt="Logo" class="company-logo">
                    @endif
                    <div class="company-name">{{ $companyName }}</div>
                    <div class="company-info">
                        @if ($companyRuc)
                            RUC: {{ $companyRuc }}
                        @endif
                        @if ($companyRuc && $employerNumber)
                            |
                        @endif
                        @if ($employerNumber)
                            Nro. Patronal: {{ $employerNumber }}
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

                {{-- Título --}}
                <div class="title">Adelanto de Salario</div>
                <div class="subtitle">Documento #{{ $advance->id }}</div>

                {{-- Monto destacado --}}
                <div class="highlight-box">
                    <div class="highlight-label">Monto del Adelanto</div>
                    <div class="highlight-amount">Gs. {{ number_format($advance->amount, 0, ',', '.') }}</div>
                </div>

                {{-- Información del empleado --}}
                <div class="section">
                    <div class="section-title">Información del Empleado</div>
                    <table class="info-table">
                        <tr>
                            <th>Nombre Completo:</th>
                            <td>{{ $advance->employee->full_name }}</td>
                        </tr>
                        <tr>
                            <th>Cédula de Identidad:</th>
                            <td>{{ $advance->employee->ci }}</td>
                        </tr>
                        <tr>
                            <th>Cargo:</th>
                            <td>{{ $advance->employee->activeContract?->position?->name ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Departamento:</th>
                            <td>{{ $advance->employee->activeContract?->position?->department?->name ?? 'N/A' }}</td>
                        </tr>
                    </table>
                </div>

                {{-- Nota legal --}}
                <div class="info-box">
                    El presente comprobante acredita el adelanto de salario solicitado por el empleado. El monto
                    indicado
                    será descontado automáticamente en la liquidación de nómina del período correspondiente.
                </div>

                {{-- Firmas --}}
                <table class="signature-table">
                    <tr>
                        <td>
                            <div class="signature-line"></div>
                            <div class="signature-label">Empleado</div>
                            <div class="signature-sublabel">{{ $advance->employee->full_name }}</div>
                            <div class="signature-sublabel">CI: {{ $advance->employee->ci }}</div>
                        </td>
                        <td>
                            <div class="signature-line"></div>
                            <div class="signature-label">Recursos Humanos</div>
                            <div class="signature-sublabel">Firma y Sello</div>
                        </td>
                    </tr>
                </table>

                {{-- Footer --}}
                <div class="footer">
                    Documento generado el {{ now()->format('d/m/Y H:i') }}
                    @if ($city)
                        | {{ $city }}, Paraguay
                    @endif
                </div>

            </div>

            {{-- Línea de corte entre copias --}}
            @if (!$loop->last)
                <div class="cut-line"></div>
            @endif
        @endforeach

    </div>
</body>

</html>
