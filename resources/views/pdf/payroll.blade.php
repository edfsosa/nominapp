<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Salario #{{ $payroll->id }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            padding: 20px 30px;
            font-size: 10px;
            line-height: 1.3;
            color: #000;
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
            width: 40%;
            padding-right: 8px;
        }

        .info-value {
            width: 60%;
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

        .text-right {
            text-align: right;
        }

        .text-bold {
            font-weight: bold;
        }

        .totals-box {
            border: 1px solid #000;
            padding: 8px;
            margin-top: 8px;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            border-bottom: 1px solid #ddd;
        }

        .totals-row:last-child {
            border-bottom: none;
            padding-top: 6px;
            margin-top: 3px;
            border-top: 1px solid #000;
        }

        .totals-label {
            font-weight: bold;
        }

        .totals-value {
            text-align: right;
        }

        .total-final {
            font-size: 11px;
            font-weight: bold;
        }

        .signature-section {
            margin-top: 30px;
            page-break-inside: avoid;
        }

        .signature-box {
            display: table;
            width: 100%;
            margin-top: 20px;
        }

        .signature-item {
            display: table-cell;
            text-align: center;
            padding: 0 15px;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-bottom: 6px;
            padding-top: 2px;
        }

        .signature-label {
            font-size: 9px;
            font-weight: bold;
        }

        .signature-sublabel {
            font-size: 8px;
            color: #666;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 6px;
        }

        .note-box {
            border: 1px solid #000;
            padding: 6px;
            margin: 12px 0;
            font-size: 8px;
        }
    </style>
</head>

<body>
    {{-- Header --}}
    <div class="header">
        <h1>RECIBO DE SALARIO #{{ $payroll->id }}</h1>
        <p>{{ $payroll->period->name }}</p>
    </div>

    {{-- Información del Empleado y Período --}}
    <div class="section">
        <div class="section-title">Información General</div>
        <div class="info-row">
            <div class="info-label">Empleado:</div>
            <div class="info-value">{{ $payroll->employee->full_name }} (CI: {{ $payroll->employee->ci }})</div>
        </div>
        <div class="info-row">
            <div class="info-label">Cargo/Dpto:</div>
            <div class="info-value">{{ $payroll->employee->position->name ?? 'N/A' }} -
                {{ $payroll->employee->position->department->name ?? 'N/A' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Período:</div>
            <div class="info-value">{{ \Carbon\Carbon::parse($payroll->period->start_date)->format('d/m/Y') }} al
                {{ \Carbon\Carbon::parse($payroll->period->end_date)->format('d/m/Y') }}
                @php
                    $freqLabels = ['monthly' => 'Mensual', 'biweekly' => 'Quincenal', 'weekly' => 'Semanal'];
                @endphp
                ({{ $freqLabels[$payroll->period->frequency] ?? $payroll->period->frequency }})
            </div>
        </div>
    </div>

    @php
        $perceptions = $payroll->items->where('type', 'perception');
        $deductions = $payroll->items->where('type', 'deduction');
    @endphp

    {{-- Percepciones --}}
    @if ($perceptions->count() > 0)
        <div class="section">
            <div class="section-title">Percepciones</div>
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 70%;">Descripción</th>
                        <th style="width: 30%;" class="text-right">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($perceptions as $item)
                        <tr>
                            <td>{{ $item->description }}</td>
                            <td class="text-right">{{ $item->formatted_amount }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Deducciones --}}
    @if ($deductions->count() > 0)
        <div class="section">
            <div class="section-title">Deducciones</div>
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 70%;">Descripción</th>
                        <th style="width: 30%;" class="text-right">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($deductions as $item)
                        <tr>
                            <td>{{ $item->description }}</td>
                            <td class="text-right">{{ $item->formatted_deduction }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Resumen de Totales --}}
    <div class="section">
        <div class="section-title">Resumen de Liquidación</div>
        <div class="totals-box">
            <div class="totals-row">
                <div class="totals-label">Salario Base:</div>
                <div class="totals-value">{{ $payroll->formatted_base_salary }}</div>
            </div>
            <div class="totals-row">
                <div class="totals-label">Total Percepciones:</div>
                <div class="totals-value">{{ $payroll->formatted_total_perceptions }}</div>
            </div>
            <div class="totals-row">
                <div class="totals-label">Salario Bruto:</div>
                <div class="totals-value">{{ $payroll->formatted_gross_salary }}</div>
            </div>
            <div class="totals-row">
                <div class="totals-label">Total Deducciones:</div>
                <div class="totals-value">{{ $payroll->formatted_total_deductions }}</div>
            </div>
            <div class="totals-row">
                <div class="totals-label total-final">SALARIO NETO A PAGAR:</div>
                <div class="totals-value total-final">{{ $payroll->formatted_net_salary }}</div>
            </div>
        </div>
    </div>

    {{-- Nota --}}
    <div class="note-box">
        <strong>NOTA:</strong> Este recibo constituye comprobante de pago válido. Conserve para sus registros.
        En caso de discrepancia, comunicarse con RRHH dentro de 48 horas.
    </div>

    {{-- Firmas --}}
    <div class="signature-section">
        <div class="section-title">Firmas</div>
        <div class="signature-box">
            <div class="signature-item">
                <div class="signature-line"></div>
                <div class="signature-label">Empleado</div>
                <div class="signature-sublabel">{{ $payroll->employee->full_name }}</div>
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
        Generado: {{ now()->format('d/m/Y H:i') }} | Recibo #{{ $payroll->id }}
    </div>
</body>

</html>
