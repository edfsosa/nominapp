<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato Laboral - {{ $contract->employee?->full_name }}</title>
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
            line-height: 1.6;
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
            font-size: 14px;
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
            width: 200px;
            padding: 4px 8px;
            border: 1px solid #000;
            font-size: 10px;
        }

        .info-value {
            display: table-cell;
            padding: 4px 8px;
            border: 1px solid #000;
            font-size: 10px;
        }

        .clause {
            margin-bottom: 12px;
            text-align: justify;
            font-size: 10px;
        }

        .clause-title {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .clause-body {
            padding-left: 5px;
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
            padding: 0 25px;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-bottom: 5px;
            padding-top: 5px;
        }

        .signature-label {
            font-size: 9px;
            font-weight: bold;
        }

        .signature-sublabel {
            font-size: 8px;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 8px;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }

        .legal-note {
            margin-top: 15px;
            font-size: 8px;
            text-align: justify;
            padding: 8px;
            border: 1px solid #ccc;
        }
    </style>
</head>

<body>
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
    <div class="title">Contrato Individual de Trabajo</div>
    <div class="subtitle">{{ \App\Models\Contract::getTypeLabel($contract->type) }} - Conforme al Codigo del Trabajo (Ley 213/93)</div>

    {{-- Datos de las Partes --}}
    <div class="section">
        <div class="section-title">Datos de las Partes</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Empleador:</div>
                <div class="info-value">{{ $companyName }}</div>
            </div>
            @if ($companyRuc)
                <div class="info-row">
                    <div class="info-label">RUC:</div>
                    <div class="info-value">{{ $companyRuc }}</div>
                </div>
            @endif
            @if ($companyAddress)
                <div class="info-row">
                    <div class="info-label">Domicilio del Empleador:</div>
                    <div class="info-value">{{ $companyAddress }}{{ $city ? ', ' . $city : '' }}</div>
                </div>
            @endif
        </div>

        <div style="height: 10px;"></div>

        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Trabajador:</div>
                <div class="info-value">{{ $contract->employee?->full_name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Cedula de Identidad:</div>
                <div class="info-value">{{ $contract->employee?->ci }}</div>
            </div>
            @if ($contract->employee?->phone)
                <div class="info-row">
                    <div class="info-label">Telefono:</div>
                    <div class="info-value">{{ $contract->employee?->phone }}</div>
                </div>
            @endif
            @if ($contract->employee?->email)
                <div class="info-row">
                    <div class="info-label">Correo Electronico:</div>
                    <div class="info-value">{{ $contract->employee?->email }}</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Datos del Contrato --}}
    <div class="section">
        <div class="section-title">Condiciones del Contrato</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Tipo de Contrato:</div>
                <div class="info-value">{{ \App\Models\Contract::getTypeLabel($contract->type) }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Fecha de Inicio:</div>
                <div class="info-value">{{ $contract->start_date->format('d/m/Y') }}</div>
            </div>
            @if ($contract->end_date)
                <div class="info-row">
                    <div class="info-label">Fecha de Finalizacion:</div>
                    <div class="info-value">{{ $contract->end_date->format('d/m/Y') }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Duracion:</div>
                    <div class="info-value">{{ $contract->duration_description }}</div>
                </div>
            @endif
            <div class="info-row">
                <div class="info-label">Cargo:</div>
                <div class="info-value">{{ $contract->position?->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Departamento:</div>
                <div class="info-value">{{ $contract->department?->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Forma de Remuneracion:</div>
                <div class="info-value">{{ \App\Models\Contract::getSalaryTypeLabel($contract->salary_type) }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">{{ $contract->salary_type === 'jornal' ? 'Jornal Diario:' : 'Salario Mensual:' }}</div>
                <div class="info-value">{{ \App\Models\Contract::formatCurrency($contract->salary) }}{{ $contract->salary_type === 'jornal' ? '/dia' : '/mes' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Modalidad de Trabajo:</div>
                <div class="info-value">{{ \App\Models\Contract::getWorkModalityLabel($contract->work_modality) }}</div>
            </div>
            @if ($contract->trial_days)
                <div class="info-row">
                    <div class="info-label">Periodo de Prueba:</div>
                    <div class="info-value">{{ $contract->trial_days }} dias (Art. 58 Codigo del Trabajo)</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Clausulas --}}
    <div class="section">
        <div class="section-title">Clausulas</div>

        <div class="clause">
            <div class="clause-title">Primera - Objeto del Contrato</div>
            <div class="clause-body">
                El EMPLEADOR contrata los servicios del TRABAJADOR para desempenar el cargo de
                <strong>{{ $contract->position?->name ?? 'N/A' }}</strong> en el departamento de
                <strong>{{ $contract->department?->name ?? 'N/A' }}</strong>, bajo modalidad
                <strong>{{ strtolower(\App\Models\Contract::getWorkModalityLabel($contract->work_modality)) }}</strong>,
                comprometiendose este ultimo a prestar sus servicios de conformidad con las instrucciones
                del empleador y las normas vigentes de la empresa.
            </div>
        </div>

        <div class="clause">
            <div class="clause-title">Segunda - Remuneracion</div>
            <div class="clause-body">
                @if ($contract->salary_type === 'jornal')
                    El EMPLEADOR se compromete a pagar al TRABAJADOR un jornal diario de
                    <strong>{{ \App\Models\Contract::formatCurrency($contract->salary) }}</strong>
                    ({{ $salaryInWords }}), pagadero conforme a la periodicidad establecida por la empresa
                    (Art. 231 del Codigo del Trabajo).
                @else
                    El EMPLEADOR se compromete a pagar al TRABAJADOR un salario mensual de
                    <strong>{{ \App\Models\Contract::formatCurrency($contract->salary) }}</strong>
                    ({{ $salaryInWords }}), pagadero conforme a la periodicidad establecida por la empresa.
                @endif
                El salario esta sujeto a las deducciones legales correspondientes (IPS 9%, y demas que correspondan).
            </div>
        </div>

        <div class="clause">
            <div class="clause-title">Tercera - Duracion del Contrato</div>
            <div class="clause-body">
                @if ($contract->type === 'indefinido')
                    El presente contrato es por <strong>TIEMPO INDEFINIDO</strong>, iniciando el
                    {{ $contract->start_date->format('d/m/Y') }}, conforme al Art. 50 del Codigo del Trabajo.
                @elseif ($contract->type === 'plazo_fijo')
                    El presente contrato es por <strong>PLAZO DETERMINADO</strong>, con vigencia desde el
                    {{ $contract->start_date->format('d/m/Y') }} hasta el {{ $contract->end_date->format('d/m/Y') }}
                    ({{ $contract->duration_description }}), conforme al Art. 53 del Codigo del Trabajo.
                    Al vencimiento del plazo, el contrato terminara sin responsabilidad para las partes, salvo que
                    se renueve por acuerdo mutuo.
                @elseif ($contract->type === 'obra_determinada')
                    El presente contrato es por <strong>OBRA DETERMINADA</strong>, con vigencia desde el
                    {{ $contract->start_date->format('d/m/Y') }} hasta la finalizacion de la obra o el
                    {{ $contract->end_date->format('d/m/Y') }}, lo que ocurra primero, conforme al Art. 54
                    del Codigo del Trabajo.
                @elseif ($contract->type === 'aprendizaje')
                    El presente contrato es de <strong>APRENDIZAJE</strong>, con vigencia desde el
                    {{ $contract->start_date->format('d/m/Y') }} hasta el {{ $contract->end_date->format('d/m/Y') }},
                    conforme a los Art. 105 y siguientes del Codigo del Trabajo.
                @elseif ($contract->type === 'pasantia')
                    El presente contrato es de <strong>PASANTIA</strong>, con vigencia desde el
                    {{ $contract->start_date->format('d/m/Y') }} hasta el {{ $contract->end_date->format('d/m/Y') }}.
                @endif
            </div>
        </div>

        @if ($contract->trial_days)
            <div class="clause">
                <div class="clause-title">Cuarta - Periodo de Prueba</div>
                <div class="clause-body">
                    Las partes acuerdan un periodo de prueba de <strong>{{ $contract->trial_days }} dias</strong>
                    contados a partir de la fecha de inicio del contrato, conforme al Art. 58 del Codigo del Trabajo.
                    Durante este periodo, cualquiera de las partes podra dar por terminado el contrato sin expresion
                    de causa y sin responsabilidad alguna.
                </div>
            </div>
        @endif

        <div class="clause">
            <div class="clause-title">{{ $contract->trial_days ? 'Quinta' : 'Cuarta' }} - Jornada de Trabajo</div>
            <div class="clause-body">
                El TRABAJADOR se obliga a cumplir la jornada de trabajo establecida por la empresa, conforme a lo
                dispuesto en los Art. 193 al 210 del Codigo del Trabajo. La jornada ordinaria no excedera de
                8 horas diarias y 48 horas semanales para la jornada diurna. Las horas extraordinarias seran
                remuneradas con el recargo legal establecido (50% en dias habiles, 100% en domingos, feriados y
                horas nocturnas).
            </div>
        </div>

        <div class="clause">
            <div class="clause-title">{{ $contract->trial_days ? 'Sexta' : 'Quinta' }} - Obligaciones del Trabajador</div>
            <div class="clause-body">
                El TRABAJADOR se obliga a: a) Prestar sus servicios con diligencia y eficiencia; b) Cumplir el
                reglamento interno de la empresa; c) Guardar reserva sobre informacion confidencial de la empresa;
                d) Comunicar oportunamente cualquier impedimento para asistir al trabajo; e) Cuidar los bienes
                y herramientas de la empresa puestos a su disposicion.
            </div>
        </div>

        <div class="clause">
            <div class="clause-title">{{ $contract->trial_days ? 'Septima' : 'Sexta' }} - Obligaciones del Empleador</div>
            <div class="clause-body">
                El EMPLEADOR se obliga a: a) Pagar la remuneracion pactada en las condiciones y plazos establecidos;
                b) Inscribir al trabajador en el Instituto de Prevision Social (IPS); c) Proporcionar condiciones
                adecuadas de trabajo conforme a la ley; d) Conceder vacaciones anuales remuneradas conforme al Art.
                218 del Codigo del Trabajo; e) Pagar el aguinaldo conforme a la Ley 772/61.
            </div>
        </div>

        <div class="clause">
            <div class="clause-title">{{ $contract->trial_days ? 'Octava' : 'Septima' }} - Disposiciones Generales</div>
            <div class="clause-body">
                Para todo lo no previsto en el presente contrato, se estara a lo dispuesto en el Codigo del Trabajo
                (Ley 213/93) y demas disposiciones legales vigentes en la Republica del Paraguay. Las partes se
                someten a la jurisdiccion de los tribunales competentes de la ciudad de {{ $city ?: 'Asuncion' }}.
            </div>
        </div>
    </div>

    {{-- Nota Legal --}}
    <div class="legal-note">
        <strong>Nota:</strong> El presente contrato se firma en dos ejemplares de un mismo tenor y a un solo efecto,
        quedando uno en poder de cada parte. En {{ $city ?: 'Asuncion' }}, Paraguay,
        a los {{ $contract->start_date->format('d') }} dias del mes de
        {{ $contract->start_date->translatedFormat('F') }} del {{ $contract->start_date->format('Y') }}.
    </div>

    {{-- Firmas --}}
    <div class="signature-section">
        <div class="signature-item">
            <div class="signature-line"></div>
            <div class="signature-label">Empleador</div>
            <div class="signature-sublabel">{{ $companyName }}</div>
            <div class="signature-sublabel">Firma y Sello</div>
        </div>
        <div class="signature-item">
            <div class="signature-line"></div>
            <div class="signature-label">Trabajador</div>
            <div class="signature-sublabel">{{ $contract->employee?->full_name }}</div>
            <div class="signature-sublabel">CI: {{ $contract->employee?->ci }}</div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        Documento generado el {{ now()->format('d/m/Y H:i') }} | Contrato #{{ $contract->id }}
        @if ($city)
            | {{ $city }}, Paraguay
        @endif
    </div>
</body>

</html>
