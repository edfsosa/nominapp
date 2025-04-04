<!DOCTYPE html>
<html>

<head>
    <title>Recibo Legal de Haberes - {{ $payroll->employee->first_name }} {{ $payroll->employee->last_name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        h2 {
            text-align: center;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 10px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }

        .employee-info {
            margin: 30px 0;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .details-table th,
        .details-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .details-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .total-row {
            font-weight: bold;
            background-color: #f1f1f1;
        }

        .signature {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="company-name">{{ config('app.name') }}</div>
        <div>RUC: 80010001-8</div>
        <div>Dirección: Av. República 123, Asunción</div>
        <div>Teléfono: +595 21 123 456</div>
    </div>

    <div class="employee-info">
        <h2>Recibo Legal de Haberes</h2>
        <p>Conforme al Art. 235 del código laboral</p>
        <p><strong>Empleado:</strong> {{ $payroll->employee->first_name }} {{ $payroll->employee->last_name }}</p>
        <p><strong>CI:</strong> {{ $payroll->employee->ci }}</p>
        <p><strong>Departamento:</strong> {{ $payroll->employee->department }}</p>
        <p><strong>Sucursal:</strong> {{ $payroll->employee->branch }}</p>
        <p><strong>Tipo de Contrato:</strong> {{ $payroll->employee->contract_type }}</p>
        <!-- Para jornaleros -->
        @if($payroll->employee->contract_type === 'jornalero')
        <p><strong>Días Trabajados:</strong> {{ $payroll->days_worked }}</p>
        @endif
        <p><strong>Fecha de Pago:</strong> {{ $payroll->payment_date }}</p>
    </div>

    <table class="details-table">
        <thead>
            <tr>
                <th>Concepto</th>
                <th>Valor (PYG)</th>
            </tr>
        </thead>
        <tbody>
            <!-- Salario Base -->
            <tr>
                <td>Salario Base</td>
                <td>{{ number_format($payroll->employee->contract_type === 'mensualero' ? $payroll->employee->salary : $payroll->employee->salary * $payroll->days_worked, 0, ',', '.') }}</td>
            </tr>

            <!-- Horas Extras -->
            @if($payroll->hours_extra > 0)
            <tr>
                <td>Horas Extras ({{ $payroll->hours_extra }} hrs)</td>
                <td>{{ number_format($payroll->hours_extra * ($payroll->employee->salary / 160), 0, ',', '.') }}</td>
            </tr>
            @endif

            <!-- Bonificaciones -->
            @if($payroll->bonuses->count() > 0)
            <tr>
                <td colspan="2"><strong>Bonificaciones</strong></td>
            </tr>
            @foreach($payroll->bonuses as $bonus)
            <tr>
                <td>{{ $bonus->name }}</td>
                <td>{{ number_format($bonus->amount, 0, ',', '.') }}</td>
            </tr>
            @endforeach
            @endif

            <!-- Deducciones -->
            <tr>
                <td colspan="2"><strong>Deducciones</strong></td>
            </tr>
            <tr>
                <td>IPS (9%)</td>
                <td>-{{ number_format($payroll->gross_salary * 0.09, 0, ',', '.') }}</td>
            </tr>
            @foreach($payroll->deductions as $deduction)
            <tr>
                <td>{{ $deduction->name }}</td>
                <td>-{{ number_format($deduction->amount, 0, ',', '.') }}</td>
            </tr>
            @endforeach

            <!-- Totales -->
            <tr class="total-row">
                <td>Salario Bruto</td>
                <td>{{ number_format($payroll->gross_salary, 0, ',', '.') }}</td>
            </tr>
            <!-- Total Deducciones -->
            <tr class="total-row">
                <td>Total Deducciones</td>
                <td>-{{ number_format(($payroll->gross_salary * 0.09) + $payroll->deductions->sum('amount'), 0, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td>Salario Neto</td>
                <td>{{ number_format($payroll->net_salary, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="signature">
        <div>
            <p>__________________________</p>
            <p>Firma del Empleado</p>
        </div>
        <div>
            <p>__________________________</p>
            <p>Firma del Responsable</p>
        </div>
    </div>

    <div style="text-align: center; margin-top: 30px; font-size: 12px; color: #777;">
        Este recibo es generado automáticamente por {{ config('app.name') }}.
    </div>
</body>

</html>