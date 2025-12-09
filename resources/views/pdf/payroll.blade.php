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

        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .items-table th {
            background-color: #f0f0f0;
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 10px;
        }

        .items-table td {
            padding: 6px 8px;
            border: 1px solid #ddd;
            font-size: 10px;
        }

        .items-table .text-right {
            text-align: right;
        }

        .totals-box {
            background-color: #f9f9f9;
            border: 2px solid #333;
            padding: 15px;
            margin-top: 20px;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #ddd;
        }

        .totals-row:last-child {
            border-bottom: none;
            padding-top: 10px;
            margin-top: 5px;
            border-top: 2px solid #333;
        }

        .totals-label {
            font-weight: bold;
        }

        .totals-value {
            text-align: right;
        }

        .total-final {
            font-size: 14px;
            font-weight: bold;
            color: #155724;
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

        .important-note {
            background-color: #fff3cd;
            border-left: 3px solid #ffc107;
            padding: 12px;
            margin: 20px 0;
            font-size: 10px;
        }

        .amount-positive {
            color: #155724;
        }

        .amount-negative {
            color: #721c24;
        }
    </style>
</head>

<body>
    {{-- Header --}}
    <div class="header">
        <h1>RECIBO DE SALARIO</h1>
        <p>Comprobante de Pago de Nómina</p>
        <p style="margin-top: 5px;"><strong>Recibo #{{ $payroll->id }}</strong></p>
    </div>

    {{-- Información del Empleado --}}
    <div class="section">
        <div class="section-title">INFORMACIÓN DEL EMPLEADO</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nombre Completo:</div>
                <div class="info-value">{{ $payroll->employee->full_name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Cédula de Identidad:</div>
                <div class="info-value">{{ $payroll->employee->ci }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Cargo:</div>
                <div class="info-value">{{ $payroll->employee->position->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Departamento:</div>
                <div class="info-value">{{ $payroll->employee->position->department->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Sucursal:</div>
                <div class="info-value">{{ $payroll->employee->branch->name ?? 'N/A' }}</div>
            </div>
        </div>
    </div>

    {{-- Información del Período --}}
    <div class="section">
        <div class="section-title">PERÍODO DE PAGO</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Período:</div>
                <div class="info-value">
                    <span class="badge badge-info">{{ $payroll->period->name }}</span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Frecuencia:</div>
                <div class="info-value">
                    {{ $payroll->period->frequency === 'monthly' ? 'Mensual' : ($payroll->period->frequency === 'biweekly' ? 'Quincenal' : 'Semanal') }}
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Fecha de Inicio:</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($payroll->period->start_date)->format('d/m/Y') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Fecha de Fin:</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($payroll->period->end_date)->format('d/m/Y') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Fecha de Generación:</div>
                <div class="info-value">{{ $payroll->generated_at->format('d/m/Y H:i') }}</div>
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
            <div class="section-title">PERCEPCIONES (INGRESOS ADICIONALES)</div>
            <table class="items-table">
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
                            <td class="text-right amount-positive">₲ {{ number_format($item->amount, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Deducciones --}}
    @if ($deductions->count() > 0)
        <div class="section">
            <div class="section-title">DEDUCCIONES (DESCUENTOS)</div>
            <table class="items-table">
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
                            <td class="text-right amount-negative">- ₲ {{ number_format($item->amount, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Resumen de Totales --}}
    <div class="section">
        <div class="section-title">RESUMEN DE LIQUIDACIÓN</div>
        <div class="totals-box">
            <div class="totals-row">
                <div class="totals-label">Salario Base:</div>
                <div class="totals-value">₲ {{ number_format($payroll->base_salary, 0, ',', '.') }}</div>
            </div>
            <div class="totals-row">
                <div class="totals-label">Total Percepciones:</div>
                <div class="totals-value amount-positive">+ ₲
                    {{ number_format($payroll->total_perceptions, 0, ',', '.') }}</div>
            </div>
            <div class="totals-row">
                <div class="totals-label">Salario Bruto:</div>
                <div class="totals-value">₲ {{ number_format($payroll->gross_salary, 0, ',', '.') }}</div>
            </div>
            <div class="totals-row">
                <div class="totals-label">Total Deducciones:</div>
                <div class="totals-value amount-negative">- ₲
                    {{ number_format($payroll->total_deductions, 0, ',', '.') }}</div>
            </div>
            <div class="totals-row">
                <div class="totals-label total-final">SALARIO NETO A PAGAR:</div>
                <div class="totals-value total-final">₲ {{ number_format($payroll->net_salary, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    {{-- Nota Importante --}}
    <div class="important-note">
        <strong>NOTA IMPORTANTE:</strong> Este recibo de salario constituye comprobante de pago válido.
        Conserve este documento para sus registros personales. En caso de discrepancia,
        comunicarse con el Departamento de Recursos Humanos dentro de las 48 horas siguientes a la recepción.
    </div>

    {{-- Firmas --}}
    <div class="signature-section">
        <div class="section-title">FIRMAS Y CONFORMIDAD</div>
        <div class="signature-box">
            <div class="signature-item">
                <div class="signature-line"></div>
                <div class="signature-label">Empleado</div>
                <div class="signature-sublabel">{{ $payroll->employee->full_name }}</div>
                <div class="signature-sublabel">CI: {{ $payroll->employee->ci }}</div>
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
        Documento generado el {{ now()->format('d/m/Y H:i') }} |
        Recibo #{{ $payroll->id }} |
        Período: {{ $payroll->period->name }}
    </div>
</body>

</html>
