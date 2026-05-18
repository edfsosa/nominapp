<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato Individual de Trabajo - {{ $contract->employee?->full_name }}</title>
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

        /* ── Encabezado empresa ── */
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

        /* ── Titulo del documento ── */
        .doc-title {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 20px 0 5px 0;
        }

        .doc-subtitle {
            text-align: center;
            font-size: 10px;
            margin-bottom: 3px;
        }

        .doc-art {
            text-align: center;
            font-size: 10px;
            margin-bottom: 18px;
        }

        /* ── Parrafo introductorio ── */
        .intro {
            text-align: justify;
            margin-bottom: 15px;
            font-size: 11px;
            line-height: 1.6;
        }

        /* ── Encabezado MODALIDADES ── */
        .section-header {
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            padding: 5px 0;
            margin-bottom: 8px;
            border-bottom: 1px solid #000;
        }

        /* ── Clausulas ── */
        .clause {
            margin-bottom: 10px;
            font-size: 11px;
            text-align: justify;
            page-break-inside: avoid;
        }

        .clause-num {
            font-weight: bold;
        }

        .clause-label {
            font-weight: bold;
            text-transform: uppercase;
        }

        .sub-item {
            padding-left: 20px;
            margin-top: 3px;
        }

        .sub-sub-item {
            padding-left: 20px;
            margin-top: 2px;
        }

        /* ── Firmas ── */
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
            font-size: 10px;
            font-weight: bold;
        }

        .signature-sublabel {
            font-size: 9px;
        }

        /* ── Cuerpo editable ── */
        .contract-body {
            margin: 20px 0;
            font-size: 11px;
            line-height: 1.6;
        }

        .contract-body p {
            margin-bottom: 8px;
            text-align: justify;
        }

        .contract-body ol,
        .contract-body ul {
            margin: 8px 0 8px 20px;
        }

        .contract-body li {
            margin-bottom: 4px;
        }

        .contract-body strong {
            font-weight: bold;
        }

        .contract-body em {
            font-style: italic;
        }

        /* ── Footer ── */
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
            <div class="company-info">{{ $companyAddress }}{{ $city ? ', ' . $city : '' }}</div>
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
    <div class="doc-title">CONTRATO INDIVIDUAL DE TRABAJO</div>

    <div class="doc-subtitle">
        @if ($contract->type === 'indefinido')
            Por Tiempo Indefinido
        @elseif ($contract->type === 'plazo_fijo')
            Por tiempo determinado o Fijo
        @elseif ($contract->type === 'obra_determinada')
            Por Obra Determinada
        @elseif ($contract->type === 'aprendizaje')
            De Aprendizaje
        @elseif ($contract->type === 'pasantia')
            De Pasantia
        @else
            {{ \App\Models\Contract::getTypeLabel($contract->type) }}
        @endif
    </div>

    <div class="doc-art">(En cumplimiento del Art. 48 del C. De T.)</div>

    {{-- Parrafo introductorio --}}
    <p class="intro">
        En la ciudad de <strong>{{ $city ?: '.............................' }}</strong>
        a los <strong>{{ $contract->start_date->format('d') }}</strong>
        dia del mes de <strong>{{ strtoupper($contract->start_date->translatedFormat('F')) }}</strong>
        del año <strong>{{ strtoupper($yearInWords) }}</strong>
        por una parte el señor/a <strong>{{ $legalRepName ?: '......................................................................' }}</strong>,
        con C.I.N.: <strong>{{ $legalRepCi ?: '.......................' }}</strong>, de .......... años de edad;
        sexo ......................... estado civil ...............................,
        de profesion ..............................., de nacionalidad ...............................
        y con domicilio para todos sus efectos legales en la casa de las calles
        <strong>{{ $companyAddress ?: '......................................................................................................' }}</strong>,
        en nombre y representacion de la firma <strong>{{ $companyName }}</strong>
        en su calidad de ............................... de la misma,
        denominado en adelante <strong>"EMPLEADOR"</strong>,
        y por la otra el señor/a
        <strong>{{ strtoupper($contract->employee?->full_name) }}</strong>
        con C.I.N. <strong>{{ $contract->employee?->ci }}</strong>,
        de <strong>{{ $employeeAge ?? '......' }}</strong> años de edad;
        sexo <strong>{{ $employeeGender ?: '...................' }}</strong>
        de estado civil <strong>{{ $employeeMaritalStatus ?: '...................' }}</strong>,
        profesion u otro oficio
        <strong>{{ $employeePosition ?: '.....................................' }}</strong>
        nacionalidad <strong>{{ $employeeNationality ?: '...................' }}</strong>
        y con domicilio en la casa de las calles
        <strong>{{ $employeeAddress ?: '......................................................................................................' }}</strong>
        denominada en adelante <strong>"TRABAJADOR"</strong>
        conviene en celebrar el presente
        <strong>CONTRATO INDIVIDUAL DE TRABAJO</strong>
        bajo las siguientes clausulas:
    </p>

    {{-- Cuerpo del contrato (cláusulas editables) --}}
    @if ($contract->body)
        <div class="contract-body">
            {!! $contract->body !!}
        </div>
    @endif

    {{-- Firmas --}}
    <div class="signature-section">
        <div class="signature-item">
            <div class="signature-line"></div>
            <div class="signature-label">Trabajador</div>
            <div class="signature-sublabel">{{ $contract->employee?->full_name }}</div>
            <div class="signature-sublabel">C.I. N.: {{ $contract->employee?->ci }}</div>
        </div>
        <div class="signature-item">
            <div class="signature-line"></div>
            <div class="signature-label">Empleador o responsable legal</div>
            <div class="signature-sublabel">{{ $companyName }}</div>
            <div class="signature-sublabel">Firma y Sello</div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        Documento generado el {{ now()->format('d/m/Y H:i') }}
        @if ($city)
            | {{ $city }}, Paraguay
        @endif
        | Contrato #{{ $contract->id }}
    </div>

</body>

</html>
