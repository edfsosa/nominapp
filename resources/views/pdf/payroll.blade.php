<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Recibo de Salario</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            margin: 20px;
        }

        h1 {
            text-align: center;
            font-size: 18px;
            margin-bottom: 0;
        }

        h2 {
            text-align: center;
            font-size: 14px;
            margin-top: 4px;
        }

        .info {
            margin-top: 20px;
            margin-bottom: 10px;
        }

        .info p {
            margin: 2px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 6px;
        }

        th {
            background-color: #f0f0f0;
        }

        .text-right {
            text-align: right;
        }

        .totales {
            margin-top: 20px;
        }

        .totales td {
            font-weight: bold;
            padding: 8px;
        }

        .signature {
            margin-top: 40px;
            width: 100%;
        }

        .signature td {
            width: 50%;
            text-align: center;
            padding-top: 40px;
        }
    </style>
</head>

<body>

    <h1>Recibo de Salario #{{ $payroll->id }}</h1>
    <h2>{{ $payroll->period->name }}</h2>

    <div class="info">
        <p><strong>Nombre:</strong> {{ $payroll->employee->full_name }}</p>
        <p><strong>CI:</strong> {{ $payroll->employee->ci }}</p>
        <p><strong>Cargo:</strong> {{ $payroll->employee->position->name ?? '---' }}</p>
        <p><strong>Sucursal:</strong> {{ $payroll->employee->branch->name ?? '---' }}</p>
        <p><strong>Fecha de emisión:</strong> {{ $payroll->generated_at->format('d/m/Y H:i') }}</p>
    </div>

    @php
        $perceptions = $payroll->items->where('type', 'perception');
        $deductions = $payroll->items->where('type', 'deduction');
    @endphp

    @if ($perceptions->count())
        <h3>Percepciones</h3>
        <table>
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th class="text-right">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($perceptions as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td class="text-right">{{ number_format($item->amount, 0, ',', '.') }} Gs</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($deductions->count())
        <h3 style="margin-top: 20px;">Deducciones</h3>
        <table>
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th class="text-right">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($deductions as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td class="text-right">-{{ number_format($item->amount, 0, ',', '.') }} Gs</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="totales">
        <tr>
            <td>Salario Base</td>
            <td class="text-right">{{ number_format($payroll->base_salary, 0, ',', '.') }} Gs</td>
        </tr>
        <tr>
            <td>Total Percepciones</td>
            <td class="text-right">{{ number_format($payroll->total_perceptions, 0, ',', '.') }} Gs</td>
        </tr>
        <tr>
            <td>Total Deducciones</td>
            <td class="text-right">-{{ number_format($payroll->total_deductions, 0, ',', '.') }} Gs</td>
        </tr>
        <tr>
            <td><strong>Salario Neto</strong></td>
            <td class="text-right"><strong>{{ number_format($payroll->net_salary, 0, ',', '.') }} Gs</strong></td>
        </tr>
    </table>

    <table class="signature">
        <tr>
            <td>Firma del Empleado</td>
            <td>Responsable RRHH</td>
        </tr>
    </table>

</body>

</html>
