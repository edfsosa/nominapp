<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
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
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 6px 0 2px 0;
        }

        .subtitle {
            text-align: center;
            font-size: 8px;
            margin-bottom: 6px;
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
            margin: 0;
        }

        table.info-table th {
            text-align: left;
            font-weight: bold;
            width: 130px;
            padding: 3px 6px;
            border: 1px solid #000;
            font-size: 9px;
        }

        table.info-table td {
            padding: 3px 6px;
            border: 1px solid #000;
            font-size: 9px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 8px;
        }

        th {
            font-weight: bold;
            background-color: #f5f5f5;
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .amount {
            font-family: 'Courier New', monospace;
            text-align: right;
        }

        .summary-section {
            margin: 6px 0;
            padding: 6px;
            border: 1px solid #000;
        }

        .summary-title {
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-size: 8px;
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
            padding: 2px 0;
        }

        .summary-label {
            font-weight: bold;
            width: 180px;
            display: inline-block;
        }

        .total-row {
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }

        .total-label {
            font-size: 10px;
            font-weight: bold;
        }

        .total-value {
            font-size: 10px;
            font-weight: bold;
        }

        .legal-note {
            margin-top: 5px;
            font-size: 7px;
            text-align: justify;
            padding: 5px 8px;
            border: 1px solid #ccc;
        }

        table.signature-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }

        table.signature-table td {
            width: 50%;
            text-align: center;
            padding: 0 25px;
            border: none;
            font-size: 9px;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-bottom: 5px;
            padding-top: 25px;
        }

        .signature-label {
            font-size: 8px;
            font-weight: bold;
        }

        .signature-sublabel {
            font-size: 7px;
        }

        .footer {
            margin-top: 6px;
            text-align: center;
            font-size: 7px;
            border-top: 1px solid #ccc;
            padding-top: 4px;
        }

        .cut-line {
            border-top: 1px dashed #555;
            margin-top: 4mm;
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
        $groupOrder = ['salary', 'other', 'viaticos', 'subsidy', null];
        $sortedGroups = collect($groupOrder)
            ->filter(fn($k) => $perceptionGroups->has($k))
            ->mapWithKeys(fn($k) => [$k => $perceptionGroups->get($k)]);
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

    {{-- Envolvente único: impide que DomPDF inserte saltos de página adentro --}}
    <div style="page-break-inside: avoid;">

        @foreach (['COPIA EMPLEADO', 'COPIA EMPRESA'] as $copyLabel)
            <div style="padding: 9mm 14mm 5mm 14mm;">

                {{-- Etiqueta de copia --}}
                <div class="copy-label">{{ $copyLabel }}</div>

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
                <div class="title">{{ $isDayLaborer ? 'Recibo de Jornal' : 'Recibo de Salario' }}</div>
                <div class="subtitle">{{ $payroll->period?->name ?? 'Sin período' }}</div>

                {{-- Información del Empleado --}}
                <div class="section">
                    <div class="section-title">Información del Empleado</div>
                    <table class="info-table">
                        <tr>
                            <th>Nombre Completo:</th>
                            <td>{{ $payroll->employee->full_name }}</td>
                        </tr>
                        <tr>
                            <th>Cédula de Identidad:</th>
                            <td>{{ $payroll->employee->ci }}</td>
                        </tr>
                        <tr>
                            <th>Cargo:</th>
                            <td>{{ $payroll->employee->activeContract?->position?->name ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Departamento:</th>
                            <td>{{ $payroll->employee->activeContract?->position?->department?->name ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Tipo de Remuneración:</th>
                            <td>{{ $isDayLaborer ? 'Jornalero (Jornal Diario)' : 'Mensualizado (Sueldo)' }}</td>
                        </tr>
                        <tr>
                            <th>Período:</th>
                            <td>
                                @if ($payroll->period)
                                    {{ $payroll->period->start_date->format('d/m/Y') }} al
                                    {{ $payroll->period->end_date->format('d/m/Y') }}
                                    ({{ $freqLabels[$payroll->period->frequency] ?? $payroll->period->frequency }})
                                @else
                                    N/A
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>

                {{-- Percepciones agrupadas por tipo --}}
                @if ($perceptions->count() > 0)
                    <div class="section">
                        <div class="section-title">Percepciones</div>
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 70%;">Descripción</th>
                                    <th style="width: 30%;" class="text-right">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sortedGroups as $groupKey => $groupItems)
                                    @if ($showGroups)
                                        <tr>
                                            <td colspan="2" style="font-weight: bold; font-size: 7px; text-transform: uppercase; color: #555; padding: 4px 0 2px 0; border-bottom: 1px solid #ddd;">
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
                                    <th style="width: 70%;">Descripción</th>
                                    <th style="width: 30%;" class="text-right">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sortedDeductionGroups as $groupKey => $groupItems)
                                    @if ($showDeductionGroups)
                                        <tr>
                                            <td colspan="2" style="font-weight: bold; font-size: 7px; text-transform: uppercase; color: #555; padding: 4px 0 2px 0; border-bottom: 1px solid #ddd;">
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

                {{-- Resumen --}}
                <div class="summary-section">
                    <div class="summary-title">Resumen</div>
                    <div class="summary-grid">
                        <div class="summary-row">
                            <div class="summary-item">
                                <span class="summary-label">{{ $isDayLaborer ? 'Jornal del Período:' : 'Salario Base:' }}</span>
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
                    <strong>Nota:</strong> Este recibo constituye comprobante de pago válido. Conserve para sus registros.
                    En caso de discrepancia, comunicarse con Recursos Humanos dentro de las 48 horas siguientes.
                </div>

                {{-- Firmas --}}
                <table class="signature-table">
                    <tr>
                        <td>
                            <div class="signature-line"></div>
                            <div class="signature-label">Empleado</div>
                            <div class="signature-sublabel">{{ $payroll->employee->full_name }}</div>
                            <div class="signature-sublabel">CI: {{ $payroll->employee->ci }}</div>
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
                    Documento generado el {{ now()->format('d/m/Y H:i') }} | Recibo #{{ $payroll->id }}
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
