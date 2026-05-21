<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte de Empleados</title>
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
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #000;
        }

        .company-logo {
            max-height: 35px;
            max-width: 100px;
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
            margin: 16px 0 4px 0;
        }

        .subtitle {
            text-align: center;
            font-size: 9px;
            color: #444;
            margin-bottom: 16px;
        }

        .section-title {
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
            padding: 4px 0;
            margin-bottom: 6px;
            border-bottom: 1px solid #000;
        }

        .section-header {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #e8e8e8;
            border: 1px solid #000;
            padding: 4px 8px;
            margin-top: 14px;
            margin-bottom: 0;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .summary-table td {
            padding: 4px 8px;
            border: 1px solid #ccc;
            font-size: 9px;
        }

        .summary-table .label {
            font-weight: bold;
            background: #f5f5f5;
            width: 35%;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        table.data-table th {
            background: #f0f0f0;
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
            padding: 4px 5px;
            border: 1px solid #ccc;
            text-align: left;
        }

        table.data-table td {
            padding: 3px 5px;
            border: 1px solid #ccc;
            font-size: 8px;
            vertical-align: middle;
        }

        table.data-table tr:nth-child(even) td {
            background: #fafafa;
        }

        .subtotal-row {
            display: table;
            width: 100%;
            border: 1px solid #ccc;
            border-top: none;
            padding: 4px 8px;
            background-color: #f0f0f0;
            font-size: 8px;
            margin-bottom: 4px;
        }

        .subtotal-row .st-item {
            display: table-cell;
            width: 50%;
        }

        .subtotal-row .st-label {
            font-weight: bold;
        }

        .grand-total {
            margin-top: 14px;
            border: 2px solid #000;
            padding: 6px 10px;
            background-color: #f5f5f5;
            page-break-inside: avoid;
        }

        .grand-total-title {
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            margin-bottom: 4px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 3px;
        }

        .grand-total-grid {
            display: table;
            width: 100%;
        }

        .grand-total-item {
            display: table-cell;
            width: 20%;
            padding: 2px 0;
        }

        .grand-total-label {
            font-weight: bold;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #666;
            font-style: italic;
        }

        .footer {
            margin-top: 24px;
            text-align: center;
            font-size: 7px;
            border-top: 1px solid #ccc;
            padding-top: 6px;
            color: #666;
        }
    </style>
</head>

<body>

    {{-- Encabezado de empresa --}}
    @if($showCompanyHeader)
    <div class="company-header">
        @if($companyLogo)
            <div><img src="{{ $companyLogo }}" class="company-logo" alt="Logo"></div>
        @endif
        <div class="company-name">{{ $companyName }}</div>
        <div class="company-info">
            @if($companyRuc) RUC: {{ $companyRuc }} @if($companyAddress) &nbsp;|&nbsp; @endif @endif
            @if($companyAddress) {{ $companyAddress }} @if($city), {{ $city }} @endif @endif
        </div>
        @if($companyPhone || $companyEmail)
        <div class="company-info">
            @if($companyPhone) Tel: {{ $companyPhone }} @endif
            @if($companyPhone && $companyEmail) &nbsp;|&nbsp; @endif
            @if($companyEmail) {{ $companyEmail }} @endif
        </div>
        @endif
    </div>
    @endif

    {{-- Título del documento --}}
    <div class="title">Reporte de Empleados</div>
    <div class="subtitle">
        Generado el {{ now()->format('d/m/Y H:i') }}
        @if($gender) &nbsp;·&nbsp; {{ $genderOptions[$gender] ?? $gender }} @endif
        @if($birthMonth) &nbsp;·&nbsp; Cumpleaños en {{ $monthOptions[$birthMonth] ?? $birthMonth }} @endif
        @if($status) &nbsp;·&nbsp; Estado: {{ $statusOptions[$status] ?? $status }} @endif
        @if($contractType) &nbsp;·&nbsp; {{ $contractTypes[$contractType] ?? $contractType }} @endif
        @if($paymentMethod) &nbsp;·&nbsp; {{ $paymentMethods[$paymentMethod] ?? $paymentMethod }} @endif
    </div>

    {{-- Resumen --}}
    <div class="section-title">Resumen</div>
    <table class="summary-table">
        <tr>
            <td class="label">Total de empleados</td>
            <td><strong>{{ $totalCount }}</strong></td>
        </tr>
        @if($byGender->isNotEmpty())
        <tr>
            <td class="label">Por género</td>
            <td>
                @foreach($genderOptions as $key => $label)
                    @if(($byGender[$key] ?? 0) > 0)
                        {{ $label }}: {{ $byGender[$key] }}
                        @if(!$loop->last) &nbsp;·&nbsp; @endif
                    @endif
                @endforeach
            </td>
        </tr>
        @endif
        @foreach($statusOptions as $key => $label)
            @if(($byStatus[$key] ?? 0) > 0)
            <tr>
                <td class="label">{{ $label }}</td>
                <td>{{ $byStatus[$key] }} empleado(s)</td>
            </tr>
            @endif
        @endforeach
        @if($avgYears !== null)
        <tr>
            <td class="label">Antigüedad promedio</td>
            <td>{{ number_format($avgYears, 1) }} años</td>
        </tr>
        @endif
    </table>

    {{-- Detalle --}}
    @if($employees->isEmpty())
        <div class="empty-state">No hay empleados para los filtros seleccionados.</div>
    @else

        @php
            $showCol = array_flip($selectedColumns);

            /**
             * Renderiza las filas de la tabla de empleados para las columnas seleccionadas.
             */
            function employeeRows($rows, $showCol, $genderOptions, $statusOptions, $contractTypes, $paymentMethods) {
                $i = 0;
                foreach ($rows as $e) {
                    $even        = $i % 2 === 1 ? 'background:#fafafa;' : '';
                    $birthDate   = $e->birth_date ? \Carbon\Carbon::parse($e->birth_date) : null;
                    $hireDate    = $e->hire_date  ? \Carbon\Carbon::parse($e->hire_date)  : null;
                    $years       = $e->years_of_service !== null ? (int) $e->years_of_service : null;
                    $salaryFmt   = $e->salary
                        ? 'Gs. ' . number_format((float) $e->salary, 0, ',', '.') . ($e->salary_type === 'jornal' ? '/d' : '/m')
                        : '—';

                    echo '<tr style="' . $even . '">';
                    if (isset($showCol['employee_name'])) echo '<td>' . e(strtoupper($e->last_name)) . ', ' . e($e->first_name) . '</td>';
                    if (isset($showCol['ci']))             echo '<td>' . e($e->ci) . '</td>';
                    if (isset($showCol['gender']))         echo '<td>' . e($genderOptions[$e->gender] ?? '—') . '</td>';
                    if (isset($showCol['age']))            echo '<td style="text-align:center;">' . ($birthDate ? $birthDate->age . 'a' : '—') . '</td>';
                    if (isset($showCol['birthday']))       echo '<td>' . ($birthDate ? $birthDate->day . ' de ' . $birthDate->locale("es")->isoFormat("MMMM") : '—') . '</td>';
                    if (isset($showCol['hire_date']))      echo '<td>' . ($hireDate ? $hireDate->format('d/m/Y') : '—') . '</td>';
                    if (isset($showCol['years_of_service'])) echo '<td style="text-align:center;">' . ($years !== null ? $years . ' año' . ($years !== 1 ? 's' : '') : '—') . '</td>';
                    if (isset($showCol['salary']))         echo '<td style="text-align:right;">' . $salaryFmt . '</td>';
                    if (isset($showCol['contract_type']))  echo '<td>' . e($contractTypes[$e->contract_type] ?? '—') . '</td>';
                    if (isset($showCol['payment_method'])) echo '<td>' . e($paymentMethods[$e->payment_method] ?? '—') . '</td>';
                    if (isset($showCol['position_name'])) echo '<td>' . e($e->position_name ?? '—') . '</td>';
                    if (isset($showCol['department_name'])) echo '<td>' . e($e->department_name ?? '—') . '</td>';
                    if (isset($showCol['branch_name']))    echo '<td>' . e($e->branch_name) . '</td>';
                    if (isset($showCol['company_name']))   echo '<td>' . e($e->company_name) . '</td>';
                    if (isset($showCol['status']))         echo '<td>' . e($statusOptions[$e->status] ?? $e->status) . '</td>';
                    if (isset($showCol['phone']))          echo '<td>' . e($e->phone ?? '—') . '</td>';
                    if (isset($showCol['email']))          echo '<td>' . e($e->email ?? '—') . '</td>';
                    echo '</tr>';
                    $i++;
                }
            }
        @endphp

        @php
            $allLabels = $columnLabels;
            $headers = array_intersect_key($allLabels, $showCol);
        @endphp

        @if($groupMode === 'flat')
            <div class="section-title">Detalle</div>
            <table class="data-table">
                <thead>
                    <tr>
                        @foreach($headers as $key => $label)
                            <th>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @php employeeRows($employees, $showCol, $genderOptions, $statusOptions, $contractTypes, $paymentMethods) @endphp
                </tbody>
            </table>

        @elseif($groupMode === 'company')
            @foreach($groups as $companyGroupName => $rows)
                <div class="section-header">{{ $companyGroupName ?: 'Sin empresa' }}</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            @foreach($headers as $key => $label)
                                <th>{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @php employeeRows($rows, $showCol, $genderOptions, $statusOptions, $contractTypes, $paymentMethods) @endphp
                    </tbody>
                </table>
                <div class="subtotal-row">
                    <div class="st-item">
                        <span class="st-label">Empleados:</span> {{ $rows->count() }}
                    </div>
                    <div class="st-item">
                        @php $groupAvg = $rows->whereNotNull('years_of_service')->avg('years_of_service'); @endphp
                        @if($groupAvg !== null)
                            <span class="st-label">Antigüedad promedio:</span> {{ number_format($groupAvg, 1) }} años
                        @endif
                    </div>
                </div>
            @endforeach
        @endif

        {{-- Gran total --}}
        <div class="grand-total">
            <div class="grand-total-title">Total General</div>
            <div class="grand-total-grid">
                <div class="grand-total-item">
                    <span class="grand-total-label">Total:</span> {{ $totalCount }} empleados
                </div>
                @foreach($genderOptions as $key => $label)
                    @if(($byGender[$key] ?? 0) > 0)
                    <div class="grand-total-item">
                        <span class="grand-total-label">{{ $label }}:</span> {{ $byGender[$key] }}
                    </div>
                    @endif
                @endforeach
                @if($avgYears !== null)
                <div class="grand-total-item">
                    <span class="grand-total-label">Antigüedad prom.:</span> {{ number_format($avgYears, 1) }} años
                </div>
                @endif
            </div>
        </div>

    @endif

    {{-- Pie de página --}}
    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }}
        @if($city) &nbsp;·&nbsp; {{ $city }}, Paraguay @endif
    </div>

</body>
</html>
