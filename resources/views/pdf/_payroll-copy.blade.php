{{-- Contenido de una copia del recibo. Variables requeridas del padre:
     $copyLabel, $payroll, $companyLogo, $companyName, $companyRuc, $companyAddress,
     $companyPhone, $companyEmail, $employerNumber, $city, $isDayLaborer, $freqLabels,
     $perceptions, $deductions --}}

{{-- Etiqueta de copia --}}
<div class="copy-label">{{ $copyLabel }}</div>

{{-- Encabezado de la Empresa --}}
<div class="company-header">
    @if ($companyLogo)
        <img src="{{ $companyLogo }}" alt="Logo" class="company-logo">
    @endif
    <div class="company-name">{{ $companyName }}</div>
    <div class="company-info">
        @if ($companyRuc)RUC: {{ $companyRuc }}@endif
        @if ($companyRuc && $employerNumber) | @endif
        @if ($employerNumber)Nro. Patronal: {{ $employerNumber }}@endif
    </div>
    @if ($companyAddress)
        <div class="company-info">{{ $companyAddress }}</div>
    @endif
    @if ($companyPhone || $companyEmail)
        <div class="company-info">
            @if ($companyPhone)Tel: {{ $companyPhone }}@endif
            @if ($companyPhone && $companyEmail) | @endif
            @if ($companyEmail){{ $companyEmail }}@endif
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
                @foreach ($perceptions as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td class="amount">{{ $item->formatted_amount }}</td>
                    </tr>
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
                @foreach ($deductions as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td class="amount">{{ $item->formatted_deduction }}</td>
                    </tr>
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
