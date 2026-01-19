<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $loan->isLoan() ? 'Préstamo' : 'Adelanto' }} #{{ $loan->id }}</title>
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
            font-size: 10px;
            line-height: 1.3;
            color: #000;
            padding: 20mm 25mm;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #000;
            padding-bottom: 8px;
        }

        .header h1 {
            font-size: 14px;
            margin-bottom: 3px;
            font-weight: bold;
        }

        .header p {
            font-size: 9px;
            margin-top: 2px;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }

        .badge-loan {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-advance {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-active {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-paid {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-cancelled {
            background-color: #e5e7eb;
            color: #374151;
        }

        .badge-defaulted {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .section {
            margin-bottom: 12px;
        }

        .section-title {
            font-weight: bold;
            font-size: 10px;
            padding: 4px 0;
            margin-bottom: 6px;
            border-bottom: 1px solid #000;
            text-transform: uppercase;
        }

        .info-row {
            display: flex;
            padding: 3px 0;
            border-bottom: 1px solid #ddd;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: bold;
            width: 40%;
            padding-right: 8px;
        }

        .info-value {
            width: 60%;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }

        .table th {
            background-color: #f5f5f5;
            padding: 4px 6px;
            border: 1px solid #000;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
        }

        .table td {
            padding: 3px 6px;
            border: 1px solid #ddd;
            font-size: 9px;
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

        .summary-box {
            border: 1px solid #000;
            padding: 8px;
            margin-top: 8px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            border-bottom: 1px solid #ddd;
        }

        .summary-row:last-child {
            border-bottom: none;
            padding-top: 6px;
            margin-top: 3px;
            border-top: 1px solid #000;
        }

        .summary-label {
            font-weight: bold;
        }

        .summary-value {
            text-align: right;
        }

        .total-final {
            font-size: 11px;
            font-weight: bold;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background-color: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin: 4px 0;
        }

        .progress-fill {
            height: 100%;
            background-color: #10b981;
        }

        .signature-section {
            margin-top: 30px;
            page-break-inside: avoid;
        }

        .signature-box {
            display: table;
            width: 100%;
            margin-top: 20px;
        }

        .signature-item {
            display: table-cell;
            text-align: center;
            padding: 0 15px;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-bottom: 6px;
            padding-top: 2px;
        }

        .signature-label {
            font-size: 9px;
            font-weight: bold;
        }

        .signature-sublabel {
            font-size: 8px;
            color: #666;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 6px;
        }

        .note-box {
            border: 1px solid #000;
            padding: 6px;
            margin: 12px 0;
            font-size: 8px;
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
    </style>
</head>

<body>
    {{-- Header --}}
    <div class="header">
        <h1>
            {{ $loan->isLoan() ? 'CONTRATO DE PRÉSTAMO' : 'COMPROBANTE DE ADELANTO DE SALARIO' }}
            #{{ $loan->id }}
        </h1>
        <p>
            <span class="badge {{ $loan->isLoan() ? 'badge-loan' : 'badge-advance' }}">
                {{ $loan->isLoan() ? 'Préstamo' : 'Adelanto' }}
            </span>
            <span class="badge badge-{{ $loan->status }}">
                {{ \App\Models\Loan::getStatusLabel($loan->status) }}
            </span>
        </p>
    </div>

    {{-- Información del Empleado --}}
    <div class="section">
        <div class="section-title">Información del Empleado</div>
        <div class="info-row">
            <div class="info-label">Nombre Completo:</div>
            <div class="info-value">{{ $loan->employee->full_name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Cédula de Identidad:</div>
            <div class="info-value">{{ $loan->employee->ci }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Cargo / Departamento:</div>
            <div class="info-value">
                {{ $loan->employee->position->name ?? 'N/A' }} -
                {{ $loan->employee->position->department->name ?? 'N/A' }}
            </div>
        </div>
    </div>

    {{-- Información del Préstamo/Adelanto --}}
    <div class="section">
        <div class="section-title">Detalles del {{ $loan->isLoan() ? 'Préstamo' : 'Adelanto' }}</div>
        <div class="info-row">
            <div class="info-label">Monto Total:</div>
            <div class="info-value text-bold">Gs. {{ number_format($loan->amount, 0, ',', '.') }}</div>
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
        @if ($loan->grantedBy)
            <div class="info-row">
                <div class="info-label">Otorgado por:</div>
                <div class="info-value">{{ $loan->grantedBy->name }}</div>
            </div>
        @endif
    </div>

    {{-- Progreso (solo si está activo o pagado) --}}
    @if ($loan->isActive() || $loan->isPaid())
        <div class="section">
            <div class="section-title">Progreso de Pago</div>
            <div class="summary-box">
                <div class="summary-row">
                    <div class="summary-label">Cuotas Pagadas:</div>
                    <div class="summary-value">{{ $loan->paid_installments_count }} de {{ $loan->installments_count }}
                    </div>
                </div>
                <div class="summary-row">
                    <div class="summary-label">Monto Pagado:</div>
                    <div class="summary-value">Gs. {{ number_format($loan->paid_amount, 0, ',', '.') }}</div>
                </div>
                <div class="summary-row">
                    <div class="summary-label">Monto Pendiente:</div>
                    <div class="summary-value">Gs. {{ number_format($loan->pending_amount, 0, ',', '.') }}</div>
                </div>
                <div class="summary-row">
                    <div class="summary-label total-final">Progreso:</div>
                    <div class="summary-value total-final">{{ $loan->progress_percentage }}%</div>
                </div>
            </div>
        </div>
    @endif

    {{-- Detalle de Cuotas (solo si tiene cuotas) --}}
    @if ($loan->installments->count() > 0)
        <div class="section">
            <div class="section-title">Detalle de Cuotas</div>
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 10%;" class="text-center">#</th>
                        <th style="width: 25%;">Vencimiento</th>
                        <th style="width: 25%;" class="text-right">Monto</th>
                        <th style="width: 20%;" class="text-center">Estado</th>
                        <th style="width: 20%;">Pagado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($loan->installments->sortBy('installment_number') as $installment)
                        <tr>
                            <td class="text-center">{{ $installment->installment_number }}</td>
                            <td class="{{ $installment->isOverdue() ? 'overdue' : '' }}">
                                {{ $installment->due_date->format('d/m/Y') }}
                                @if ($installment->isOverdue())
                                    (Vencida)
                                @endif
                            </td>
                            <td class="text-right">Gs. {{ number_format($installment->amount, 0, ',', '.') }}</td>
                            <td class="text-center">
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
        <div class="note-box">
            <strong>NOTAS:</strong> {{ $loan->notes }}
        </div>
    @endif

    {{-- Términos y Condiciones --}}
    <div class="note-box">
        <strong>TÉRMINOS Y CONDICIONES:</strong><br>
        1. El empleado se compromete a pagar las cuotas establecidas en las fechas indicadas.<br>
        2. Los pagos serán descontados automáticamente del salario del empleado.<br>
        3. En caso de terminación de la relación laboral, el saldo pendiente será descontado de la liquidación
        final.<br>
        4. Este documento constituye un acuerdo vinculante entre las partes.
    </div>

    {{-- Firmas --}}
    <div class="signature-section">
        <div class="section-title">Firmas</div>
        <div class="signature-box">
            <div class="signature-item">
                <div class="signature-line"></div>
                <div class="signature-label">Empleado</div>
                <div class="signature-sublabel">{{ $loan->employee->full_name }}</div>
                <div class="signature-sublabel">CI: {{ $loan->employee->ci }}</div>
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
        Generado: {{ now()->format('d/m/Y H:i') }} |
        {{ $loan->isLoan() ? 'Préstamo' : 'Adelanto' }} #{{ $loan->id }} |
        {{ $loan->employee->full_name }}
    </div>
</body>

</html>
