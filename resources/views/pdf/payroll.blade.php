<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Recibo de Salario</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #333;
        }

        .header,
        .footer {
            text-align: center;
        }

        .company-info,
        .employee-info {
            margin-bottom: 20px;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 5px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 3px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #999;
            padding: 6px 8px;
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .total-row {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .signature {
            margin-top: 40px;
        }

        .signature-line {
            border-top: 1px solid #000;
            width: 200px;
            margin: 0 auto;
            text-align: center;
            font-size: 10px;
        }
    </style>
</head>

<body>

    <div class="header">
        <h2>Recibo de Salario</h2>
        <p>Periodo: {{ $payroll->period->start_date->format('d/m/Y') }} al
            {{ $payroll->period->end_date->format('d/m/Y') }}</p>
    </div>

    <div class="company-info">
        <div class="section-title">Datos de la Empresa</div>
        <p><strong>Nombre:</strong> Mi Empresa S.A.</p>
        <p><strong>RUC:</strong> 8000000-1</p>
        <p><strong>Dirección:</strong> Asunción, Paraguay</p>
    </div>

    <div class="employee-info">
        <div class="section-title">Datos del Empleado</div>
        <p><strong>Nombre y apellido:</strong> {{ $payroll->employee->first_name }} {{ $payroll->employee->last_name }}
        </p>
        <p><strong>Cédula:</strong> {{ $payroll->employee->ci ?? '---' }}</p>
        <p><strong>Cargo:</strong> {{ $payroll->employee->position->name ?? '---' }}</p>
        <p><strong>Fecha de ingreso:</strong> {{ optional($payroll->employee->hire_date)->format('d/m/Y') }}</p>
    </div>

    <div>
        <div class="section-title">Detalle de Haberes</div>
        <table>
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th class="text-right">Monto (Gs)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payroll->items->where('type', 'perception') as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td class="text-right">{{ number_format($item->amount, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td>Total Haberes</td>
                    <td class="text-right">{{ number_format($payroll->total_perceptions, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div>
        <div class="section-title" style="margin-top: 20px;">Detalle de Descuentos</div>
        <table>
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th class="text-right">Monto (Gs)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payroll->items->where('type', 'deduction') as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td class="text-right">{{ number_format($item->amount, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td>Total Descuentos</td>
                    <td class="text-right">{{ number_format($payroll->total_deductions, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div>
        <div class="section-title" style="margin-top: 20px;">Total Neto a Cobrar</div>
        <table>
            <tr class="total-row">
                <td class="text-right" colspan="2">Gs. {{ number_format($payroll->net_salary, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="signature">
        <p class="signature-line">Firma del Empleado</p>
    </div>

    <div class="footer">
        <p>Generado por Nominapp</p>
        <p>
            Fecha de emisión: {{ now()->format('d/m/Y H:i') }}
        </p>
    </div>

</body>

</html>
