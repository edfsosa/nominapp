<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Salario #{{ $payroll->id }}</title>
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

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 6px;
            font-size: 9px;
        }

        th {
            font-weight: bold;
            background-color: #f5f5f5;
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .text-bold {
            font-weight: bold;
        }

        .amount {
            font-family: 'Courier New', monospace;
            text-align: right;
        }

        .summary-section {
            margin: 15px 0;
            padding: 12px;
            border: 1px solid #000;
        }

        .summary-title {
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
            font-size: 10px;
        }

        .summary-grid {
            display: table;
            width: 100%;
        }

        .summary-row {
            display: table-row;
        }

        .summary-item {
            display: table-cell;
            padding: 4px 0;
        }

        .summary-label {
            font-weight: bold;
            width: 200px;
            display: inline-block;
        }

        .summary-value {
            text-align: right;
        }

        .total-row {
            border-top: 1px solid #000;
            padding-top: 8px;
            margin-top: 8px;
        }

        .total-label {
            font-size: 12px;
            font-weight: bold;
        }

        .total-value {
            font-size: 12px;
            font-weight: bold;
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

        .legal-note {
            margin-top: 15px;
            font-size: 9px;
            text-align: justify;
            padding: 10px;
            border: 1px solid #ccc;
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
    @php
        $freqLabels = ['monthly' => 'Mensual', 'biweekly' => 'Quincenal', 'weekly' => 'Semanal'];
        $perceptionTypeLabels = ['salary' => 'Salariales', 'viaticos' => 'Viáticos', 'subsidy' => 'Subsidios', 'other' => 'Otros'];
        $deductionTypeLabels  = ['legal' => 'Legales', 'judicial' => 'Judiciales', 'voluntary' => 'Voluntarias'];
        $perceptions = $payroll->items->where('type', 'perception');
        $deductions  = $payroll->items->where('type', 'deduction');
        $isDayLaborer = $payroll->employee->employment_type === 'day_laborer';
        // Agrupar percepciones por tipo; las sin tipo (HE, bonif. familiar) van al grupo null
        $perceptionGroups = $perceptions->groupBy('perception_type');
        // Orden de presentación: salariales primero, luego el resto
        $groupOrder = ['salary', 'other', 'viaticos', 'subsidy', null];
        $sortedGroups = collect($groupOrder)
            ->filter(fn($k) => $perceptionGroups->has($k))
            ->mapWithKeys(fn($k) => [$k => $perceptionGroups->get($k)]);
        // Añadir grupos con tipos no contemplados en el orden (por seguridad)
        $perceptionGroups->each(function($items, $key) use (&$sortedGroups, $groupOrder) {
            if (!in_array($key, $groupOrder, true)) {
                $sortedGroups->put($key, $items);
            }
        });
        $showGroups = $sortedGroups->count() > 1;

        // Agrupar deducciones por tipo; las sin tipo (ausentismo, cuotas de préstamo) van al grupo null
        $deductionGroups = $deductions->groupBy('deduction_type');
        $deductionGroupOrder = ['legal', 'judicial', 'voluntary', null];
        $sortedDeductionGroups = collect($deductionGroupOrder)
            ->filter(fn($k) => $deductionGroups->has($k))
            ->mapWithKeys(fn($k) => [$k => $deductionGroups->get($k)]);
        $deductionGroups->each(function($items, $key) use (&$sortedDeductionGroups, $deductionGroupOrder) {
            if (!in_array($key, $deductionGroupOrder, true)) {
                $sortedDeductionGroups->put($key, $items);
            }
        });
        $showDeductionGroups = $sortedDeductionGroups->count() > 1;
    @endphp

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
    <div class="title">{{ $isDayLaborer ? 'Recibo de Jornal' : 'Recibo de Salario' }}</div>
    <div class="subtitle">{{ $payroll->period?->name ?? 'Sin período' }}</div>

    {{-- Informacion del Empleado --}}
    <div class="section">
        <div class="section-title">Informacion del Empleado</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nombre Completo:</div>
                <div class="info-value">{{ $payroll->employee->full_name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Cedula de Identidad:</div>
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
                <div class="info-label">Tipo de Remuneracion:</div>
                <div class="info-value">{{ $isDayLaborer ? 'Jornalero (Jornal Diario)' : 'Mensualizado (Sueldo)' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Periodo:</div>
                <div class="info-value">
                    @if ($payroll->period)
                        {{ \Carbon\Carbon::parse($payroll->period->start_date)->format('d/m/Y') }} al
                        {{ \Carbon\Carbon::parse($payroll->period->end_date)->format('d/m/Y') }}
                        ({{ $freqLabels[$payroll->period->frequency] ?? $payroll->period->frequency }})
                    @else
                        N/A
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Percepciones agrupadas por tipo --}}
    @if ($perceptions->count() > 0)
        <div class="section">
            <div class="section-title">Percepciones</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 70%;">Descripcion</th>
                        <th style="width: 30%;" class="text-right">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sortedGroups as $groupKey => $groupItems)
                        {{-- Subencabezado de grupo solo si hay más de un tipo --}}
                        @if ($showGroups)
                            <tr>
                                <td colspan="2" style="font-weight: bold; font-size: 9px; text-transform: uppercase; color: #555; padding: 6px 0 2px 0; border-bottom: 1px solid #ddd;">
                                    {{ $perceptionTypeLabels[$groupKey] ?? 'Otros' }}
                                </td>
                            </tr>
                        @endif
                        @foreach ($groupItems as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td class="amount">{{ $item->formatted_amount }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Deducciones agrupadas por tipo --}}
    @if ($deductions->count() > 0)
        <div class="section">
            <div class="section-title">Deducciones</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 70%;">Descripcion</th>
                        <th style="width: 30%;" class="text-right">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sortedDeductionGroups as $groupKey => $groupItems)
                        @if ($showDeductionGroups)
                            <tr>
                                <td colspan="2" style="font-weight: bold; font-size: 9px; text-transform: uppercase; color: #555; padding: 6px 0 2px 0; border-bottom: 1px solid #ddd;">
                                    {{ $deductionTypeLabels[$groupKey] ?? 'Otras' }}
                                </td>
                            </tr>
                        @endif
                        @foreach ($groupItems as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td class="amount">{{ $item->formatted_deduction }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Resumen de Liquidacion --}}
    <div class="summary-section">
        <div class="summary-title">Resumen de Liquidacion</div>
        <div class="summary-grid">
            <div class="summary-row">
                <div class="summary-item">
                    <span class="summary-label">{{ $isDayLaborer ? 'Jornal del Periodo:' : 'Salario Base:' }}</span>
                    {{ $payroll->formatted_base_salary }}
                </div>
            </div>
            <div class="summary-row">
                <div class="summary-item">
                    <span class="summary-label">Total Percepciones:</span>
                    {{ $payroll->formatted_total_perceptions }}
                </div>
            </div>
            <div class="summary-row">
                <div class="summary-item">
                    <span class="summary-label">Salario Bruto:</span>
                    {{ $payroll->formatted_gross_salary }}
                </div>
            </div>
            <div class="summary-row">
                <div class="summary-item">
                    <span class="summary-label">Total Deducciones:</span>
                    {{ $payroll->formatted_total_deductions }}
                </div>
            </div>
            <div class="summary-row total-row">
                <div class="summary-item">
                    <span class="summary-label total-label">SALARIO NETO A PAGAR:</span>
                    <strong class="total-value">{{ $payroll->formatted_net_salary }}</strong>
                </div>
            </div>
        </div>
    </div>

    {{-- Nota Legal --}}
    <div class="legal-note">
        <strong>Nota:</strong> Este recibo constituye comprobante de pago valido. Conserve para sus registros.
        En caso de discrepancia, comunicarse con Recursos Humanos dentro de las 48 horas siguientes.
    </div>

    {{-- Firmas --}}
    <div class="signature-section">
        <div class="signature-item">
            <div class="signature-line"></div>
            <div class="signature-label">Empleado</div>
            <div class="signature-sublabel">{{ $payroll->employee->full_name }}</div>
            <div class="signature-sublabel">CI: {{ $payroll->employee->ci }}</div>
        </div>
        <div class="signature-item">
            <div class="signature-line"></div>
            <div class="signature-label">Recursos Humanos</div>
            <div class="signature-sublabel">Firma y Sello</div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        Documento generado el {{ now()->format('d/m/Y H:i') }} | Recibo #{{ $payroll->id }}
        @if ($city)
            | {{ $city }}, Paraguay
        @endif
    </div>
</body>

</html>
