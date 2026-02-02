<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $loan->isLoan() ? 'Contrato de Prestamo' : 'Comprobante de Adelanto' }} #{{ $loan->id }}</title>
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
            padding: 3px 0;
        }

        .summary-label {
            font-weight: bold;
            width: 160px;
            display: inline-block;
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
            text-align: center;
            font-size: 9px;
        }

        th {
            font-weight: bold;
            background-color: #f5f5f5;
        }

        .text-left {
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .amount {
            font-family: 'Courier New', monospace;
            text-align: right;
        }

        .total-row td {
            font-weight: bold;
        }

        .status-paid {
            color: #065f46;
        }

        .status-pending {
            color: #92400e;
        }

        .status-cancelled {
            color: #6b7280;
        }

        .overdue {
            color: #991b1b;
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
    {{-- Encabezado de la Empresa --}}
    <div class="company-header">
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
    <div class="title">
        {{ $loan->isLoan() ? 'Contrato de Prestamo' : 'Comprobante de Adelanto de Salario' }}
    </div>
    <div class="subtitle">Documento #{{ $loan->id }}</div>

    {{-- Informacion del Empleado --}}
    <div class="section">
        <div class="section-title">Informacion del Empleado</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nombre Completo:</div>
                <div class="info-value">{{ $loan->employee->full_name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Cedula de Identidad:</div>
                <div class="info-value">{{ $loan->employee->ci }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Cargo:</div>
                <div class="info-value">{{ $loan->employee->position->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Departamento:</div>
                <div class="info-value">{{ $loan->employee->position->department->name ?? 'N/A' }}</div>
            </div>
        </div>
    </div>

    {{-- Detalles del Prestamo/Adelanto --}}
    <div class="section">
        <div class="section-title">Detalles del {{ $loan->isLoan() ? 'Prestamo' : 'Adelanto' }}</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Monto Total:</div>
                <div class="info-value"><strong>Gs. {{ number_format($loan->amount, 0, ',', '.') }}</strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">Cantidad de Cuotas:</div>
                <div class="info-value">{{ $loan->installments_count }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Monto por Cuota:</div>
                <div class="info-value">Gs. {{ number_format($loan->installment_amount, 0, ',', '.') }}</div>
            </div>
            @if ($loan->reason)
                <div class="info-row">
                    <div class="info-label">Motivo:</div>
                    <div class="info-value">{{ $loan->reason }}</div>
                </div>
            @endif
            @if ($loan->granted_at)
                <div class="info-row">
                    <div class="info-label">Fecha de Otorgamiento:</div>
                    <div class="info-value">{{ $loan->granted_at->format('d/m/Y') }}</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Progreso de Pago (solo si esta activo o pagado) --}}
    @if ($loan->isActive() || $loan->isPaid())
        <div class="summary-section">
            <div class="summary-title">Progreso de Pago</div>
            <div class="summary-grid">
                <div class="summary-row">
                    <div class="summary-item">
                        <span class="summary-label">Cuotas Pagadas:</span>
                        {{ $loan->paid_installments_count }} de {{ $loan->installments_count }}
                    </div>
                </div>
                <div class="summary-row">
                    <div class="summary-item">
                        <span class="summary-label">Monto Pagado:</span>
                        Gs. {{ number_format($loan->paid_amount, 0, ',', '.') }}
                    </div>
                </div>
                <div class="summary-row">
                    <div class="summary-item">
                        <span class="summary-label">Monto Pendiente:</span>
                        Gs. {{ number_format($loan->pending_amount, 0, ',', '.') }}
                    </div>
                </div>
                <div class="summary-row">
                    <div class="summary-item">
                        <span class="summary-label">Progreso:</span>
                        <strong>{{ $loan->progress_percentage }}%</strong>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Detalle de Cuotas --}}
    @if ($loan->installments->count() > 0)
        <div class="section">
            <div class="section-title">Detalle de Cuotas</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 10%;">#</th>
                        <th style="width: 25%;">Vencimiento</th>
                        <th style="width: 25%;">Monto</th>
                        <th style="width: 20%;">Estado</th>
                        <th style="width: 20%;">Fecha de Pago</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($loan->installments->sortBy('installment_number') as $installment)
                        <tr>
                            <td>{{ $installment->installment_number }}</td>
                            <td class="{{ $installment->isOverdue() ? 'overdue' : '' }}">
                                {{ $installment->due_date->format('d/m/Y') }}
                                @if ($installment->isOverdue())
                                    (Vencida)
                                @endif
                            </td>
                            <td class="amount">Gs. {{ number_format($installment->amount, 0, ',', '.') }}</td>
                            <td>
                                <span class="status-{{ $installment->status }}">
                                    {{ \App\Models\LoanInstallment::getStatusLabel($installment->status) }}
                                </span>
                            </td>
                            <td>
                                @if ($installment->paid_at)
                                    {{ $installment->paid_at->format('d/m/Y') }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Notas --}}
    @if ($loan->notes)
        <div class="note-section">
            <div class="note-title">Observaciones:</div>
            <p>{{ $loan->notes }}</p>
        </div>
    @endif

    {{-- Firmas --}}
    <div class="signature-section">
        <div class="signature-item">
            <div class="signature-line"></div>
            <div class="signature-label">Empleado</div>
            <div class="signature-sublabel">{{ $loan->employee->full_name }}</div>
            <div class="signature-sublabel">CI: {{ $loan->employee->ci }}</div>
        </div>
        <div class="signature-item">
            <div class="signature-line"></div>
            <div class="signature-label">Recursos Humanos</div>
            <div class="signature-sublabel">Firma y Sello</div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        Documento generado el {{ now()->format('d/m/Y H:i') }}
        @if ($city)
            | {{ $city }}, Paraguay
        @endif
    </div>
</body>

</html>
