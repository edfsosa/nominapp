<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte de Salarios</title>
    <style>
        @page {
            size: A4 landscape;
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
            padding: 12mm 15mm;
        }

        .company-header {
            text-align: center;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #000;
        }

        .company-logo {
            max-height: 36px;
            max-width: 110px;
            margin-bottom: 6px;
        }

        .company-name {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .company-info {
            font-size: 8px;
        }

        .title {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 14px 0 3px 0;
        }

        .subtitle {
            text-align: center;
            font-size: 9px;
            margin-bottom: 14px;
        }

        .section-title {
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
            padding: 4px 0;
            margin-bottom: 6px;
            border-bottom: 1px solid #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        thead th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 8px;
            text-align: center;
            padding: 4px 3px;
            border: 1px solid #ccc;
        }

        tbody td {
            padding: 3px 3px;
            border: 1px solid #ddd;
            font-size: 8px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .row-total {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 7.5px;
        }

        .badge-success { background-color: #d1fae5; color: #065f46; }
        .badge-warning { background-color: #fef3c7; color: #92400e; }
        .badge-info    { background-color: #dbeafe; color: #1e40af; }
        .badge-gray    { background-color: #f3f4f6; color: #374151; }

        .summary {
            display: table;
            width: 100%;
            margin-bottom: 16px;
        }

        .summary-item {
            display: table-cell;
            text-align: center;
            padding: 6px 8px;
            border: 1px solid #ddd;
            background: #fafafa;
        }

        .summary-value {
            font-size: 11px;
            font-weight: bold;
        }

        .summary-label {
            font-size: 7.5px;
            color: #555;
            margin-top: 2px;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 7.5px;
            border-top: 1px solid #ccc;
            padding-top: 8px;
        }
    </style>
</head>

<body>

    {{-- Encabezado de empresa --}}
    @if($companyName)
    <div class="company-header">
        @if($companyLogo)
            <div><img src="{{ $companyLogo }}" class="company-logo" alt="Logo"></div>
        @endif
        <div class="company-name">{{ $companyName }}</div>
        <div class="company-info">
            @if($companyRuc) RUC: {{ $companyRuc }} @endif
            @if($companyAddress) &nbsp;|&nbsp; {{ $companyAddress }} @endif
        </div>
    </div>
    @endif

    {{-- Título --}}
    <div class="title">Reporte de Salarios</div>
    <div class="subtitle">
        @if($period)
            Planilla: {{ $period->name }}
            ({{ $period->start_date->format('d/m/Y') }} — {{ $period->end_date->format('d/m/Y') }})
        @else
            Todas las planillas
        @endif
        &nbsp;|&nbsp; Generado el {{ now()->format('d/m/Y H:i') }}
    </div>

    {{-- Resumen --}}
    <div class="summary">
        <div class="summary-item">
            <div class="summary-value">{{ $totalEmployees }}</div>
            <div class="summary-label">Empleados</div>
        </div>
        <div class="summary-item">
            <div class="summary-value">Gs. {{ number_format($totalBaseSalary, 0, ',', '.') }}</div>
            <div class="summary-label">Total Salario Base</div>
        </div>
        <div class="summary-item">
            <div class="summary-value">Gs. {{ number_format($totalPerceptions, 0, ',', '.') }}</div>
            <div class="summary-label">Total Percepciones</div>
        </div>
        <div class="summary-item">
            <div class="summary-value">Gs. {{ number_format($totalDeductions, 0, ',', '.') }}</div>
            <div class="summary-label">Total Deducciones</div>
        </div>
        <div class="summary-item">
            <div class="summary-value">Gs. {{ number_format($totalNet, 0, ',', '.') }}</div>
            <div class="summary-label">Total Neto a Pagar</div>
        </div>
    </div>

    {{-- Tabla principal --}}
    <table>
        <thead>
            <tr>
                <th style="width:14%">Empleado</th>
                <th style="width:6%">CI</th>
                <th style="width:7%">Sucursal</th>
                <th style="width:9%">Cargo</th>
                <th style="width:8%">Salario Base</th>
                <th style="width:7%">+Percepciones</th>
                <th style="width:7%">IPS</th>
                <th style="width:8%">Prést./Adelantos</th>
                <th style="width:6%">Judiciales</th>
                <th style="width:6%">Voluntarias</th>
                <th style="width:8%">-Deducciones</th>
                <th style="width:8%">Neto a Pagar</th>
                <th style="width:6%">Método</th>
                <th style="width:6%">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payrolls as $p)
            <tr>
                <td>{{ $p->last_name }}, {{ $p->first_name }}</td>
                <td class="text-center">{{ $p->ci }}</td>
                <td class="text-center">{{ $p->branch_name }}</td>
                <td>{{ $p->position_name ?? '—' }}</td>
                <td class="text-right">{{ number_format($p->base_salary, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($p->total_perceptions, 0, ',', '.') }}</td>
                <td class="text-right">{{ $p->ips_amount > 0 ? number_format($p->ips_amount, 0, ',', '.') : '—' }}</td>
                <td class="text-right">{{ $p->loan_amount > 0 ? number_format($p->loan_amount, 0, ',', '.') : '—' }}</td>
                <td class="text-right">{{ $p->judicial_amount > 0 ? number_format($p->judicial_amount, 0, ',', '.') : '—' }}</td>
                <td class="text-right">{{ $p->voluntary_amount > 0 ? number_format($p->voluntary_amount, 0, ',', '.') : '—' }}</td>
                <td class="text-right">{{ number_format($p->total_deductions, 0, ',', '.') }}</td>
                <td class="text-right" style="font-weight:bold">{{ number_format($p->net_salary, 0, ',', '.') }}</td>
                <td class="text-center">
                    @php $pm = $p->payment_method; @endphp
                    <span class="badge {{ $pm === 'cash' ? 'badge-warning' : 'badge-info' }}">
                        {{ $pm === 'cash' ? 'Efectivo' : 'Transferencia' }}
                    </span>
                </td>
                <td class="text-center">
                    @php
                        $statusClass = match($p->status) {
                            'paid'      => 'badge-success',
                            'disbursed' => 'badge-info',
                            'approved'  => 'badge-warning',
                            default     => 'badge-gray',
                        };
                        $statusLabel = \App\Models\Payroll::getStatusLabels()[$p->status] ?? $p->status;
                    @endphp
                    <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="14" class="text-center" style="padding:10px; color:#888;">
                    Sin registros para los filtros seleccionados.
                </td>
            </tr>
            @endforelse

            {{-- Fila de totales --}}
            @if($payrolls->isNotEmpty())
            <tr class="row-total">
                <td colspan="4" style="text-align:right; padding-right:6px;">TOTALES</td>
                <td class="text-right">{{ number_format($totalBaseSalary, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totalPerceptions, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totalIps, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totalLoans, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totalJudicial, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totalVoluntary, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totalDeductions, 0, ',', '.') }}</td>
                <td class="text-right" style="font-weight:bold">{{ number_format($totalNet, 0, ',', '.') }}</td>
                <td colspan="2"></td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        Reporte generado el {{ now()->format('d/m/Y \a \l\a\s H:i') }} · {{ config('app.name') }}
    </div>

</body>
</html>
