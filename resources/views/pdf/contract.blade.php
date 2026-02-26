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
        En la ciudad de <strong>{{ strtoupper($city ?: 'ASUNCION') }}</strong>
        a los <strong>{{ $contract->start_date->format('d') }}</strong>
        dia del mes de <strong>{{ strtoupper($contract->start_date->translatedFormat('F')) }}</strong>
        del ano <strong>{{ strtoupper($yearInWords) }}</strong>
        por una parte el senor ................................................................................,
        con C.I.N.: ..............................., de .......... anos de edad;
        sexo ......................... estado civil ...............................,
        de profesion ..............................., de nacionalidad ...............................
        y con domicilio para todos sus efectos legales en la casa de las calles
        ......................................................................................................
        del Barrio ....................................... de la ciudad de ...............................,
        en nombre y representacion de la firma <strong>{{ strtoupper($companyName) }}</strong>
        en su calidad de ............................... de la misma,
        denominado en adelante <strong>"EMPLEADOR"</strong>,
        y por la otra el senor/a
        <strong>{{ strtoupper($contract->employee?->full_name) }}</strong>
        con C.I.N. <strong>{{ $contract->employee?->ci }}</strong>,
        de <strong>{{ $employeeAge ?? '......' }}</strong> anos de edad;
        sexo ......................... de estado civil ...............................,
        profesion u otro oficio
        <strong>{{ strtoupper($contract->position?->name ?? '..............................') }}</strong>
        nacionalidad ............................... y con domicilio en la casa de las calles
        ......................................................................................................
        de la ciudad de ...............................
        denominada en adelante <strong>"TRABAJADOR"</strong>
        conviene en celebrar el presente
        <strong>CONTRATO INDIVIDUAL DE TRABAJO</strong>
        bajo las siguientes clausulas:
    </p>

    {{-- MODALIDADES --}}
    <div class="section-header">MODALIDADES</div>

    {{-- PRIMERA --}}
    <div class="clause">
        <span class="clause-num">PRIMERA:</span>&nbsp;&nbsp;&nbsp;
        a- Clase de trabajo o servicio a ejecutar:
        <strong>{{ strtoupper($contract->position?->name ?? '..............................') }}</strong><br>
        <div class="sub-item">
            b- Lugar o lugares de presentacion: En el local de la empresa sito en
            {{ $companyAddress ?: 'Ruta ......................................................' }}
            de la ciudad de <strong>{{ strtoupper($city ?: 'ASUNCION') }}</strong>
            y/o en los lugares designados por la empresa para la ejecucion de sus labores,
            dentro y fuera del Radio Urbano establecido dentro del area central del pais,
            conforme a la naturaleza del trabajo. -
        </div>
    </div>

    {{-- SEGUNDA --}}
    <div class="clause">
        <span class="clause-num">SEGUNDA:</span>&nbsp;&nbsp;&nbsp;
        <span class="clause-label">FORMA DE CONTRATO</span><br>
        <div class="sub-item">
            a- Por unidad de tiempo &nbsp;&nbsp;&nbsp;&nbsp;<strong>Si</strong>
        </div>
    </div>

    {{-- TERCERA --}}
    <div class="clause">
        <span class="clause-num">TERCERA:</span> <span class="clause-label">REMUNERACION CONVENIDA</span><br>
        <div class="sub-item">
            a-&nbsp; Monto convenido inicialmente:
            <strong>{{ \App\Models\Contract::formatCurrency($contract->salary) }}</strong>
            ( {{ ucfirst($salaryInWords) }} ).-
        </div>
    </div>

    {{-- CUARTA --}}
    <div class="clause">
        <span class="clause-num">CUARTA:</span> <span class="clause-label">PLAZO DEL CONTRATO</span><br>
        <div class="sub-item">
            @if ($contract->type === 'indefinido')
                a-&nbsp; Indefinido: <strong>SI</strong><br>
                DESDE:
                <strong>
                    {{ strtoupper(
                        $contract->start_date->format('d') . ' DE ' .
                        $contract->start_date->translatedFormat('F') . ' DE ' .
                        $contract->start_date->format('Y')
                    ) }}
                </strong>
            @else
                a-&nbsp; Determinado o fijo: <strong>SI</strong>
                @if ($durationDescription) por <strong>{{ $durationDescription }}</strong> @endif.<br>
                DESDE:
                <strong>
                    {{ strtoupper(
                        $contract->start_date->format('d') . ' DE ' .
                        $contract->start_date->translatedFormat('F') . ' DE ' .
                        $contract->start_date->format('Y')
                    ) }}
                </strong>
                &nbsp;&nbsp; HASTA:
                <strong>
                    @if ($contract->end_date)
                        {{ strtoupper(
                            $contract->end_date->format('d') . ' DE ' .
                            $contract->end_date->translatedFormat('F') . ' DE ' .
                            $contract->end_date->format('Y')
                        ) }}
                    @else
                        .......................................
                    @endif
                </strong>
            @endif
        </div>
    </div>

    {{-- QUINTA --}}
    <div class="clause">
        <span class="clause-num">QUINTA:</span>&nbsp;&nbsp;&nbsp;
        a) <span class="clause-label">DURACION DE LA JORNADA</span><br>
        <div class="sub-item">
            1.&nbsp;<strong>{{ $shiftTypeLabel }}</strong>&nbsp;: <strong>SI</strong><br>
            La duracion de la jornada de trabajo sera de
            <strong>{{ $weeklyHours }} ({{ strtoupper($weeklyHoursInWords) }})</strong>
            horas semanales.
        </div>

        <br>

        <div class="sub-item">
            b) <span class="clause-label">DIVISION DE LA JORNADA</span><br>
            <div class="sub-sub-item">
                Por la maniana: de ..........hs. a ..........hs.<br>
                Por la tarde: de ..........hs. a ..........hs.<br>
                Sabados de:
                @if ($saturdayDay)
                    <strong>{{ substr($saturdayDay->start_time, 0, 5) }} hs.</strong>
                    a
                    <strong>{{ substr($saturdayDay->end_time, 0, 5) }} hs.</strong>
                @else
                    ..........hs. a ..........hs.
                @endif
                <br>
                Por la noche: de ..........hs. a ..........hs.<br>
                Horario continuado: de
                @if ($weekdayDay)
                    <strong>{{ substr($weekdayDay->start_time, 0, 5) }} hs.</strong>
                    a
                    <strong>{{ substr($weekdayDay->end_time, 0, 5) }} hs.</strong>
                @else
                    ..........hs. a ..........hs.
                @endif
                <br>
                Periodo intermedio de descanso:
                @if ($breakMinutes > 0)
                    <strong>{{ $breakMinutes }} minutos</strong> para el almuerzo
                @else
                    ......... minutos para el almuerzo
                @endif
                <br>
                Descanso semanal: Domingos y Feriados.
                Por el sistema de trabajo el empleador goza de un dia libre a la semana
                siendo esta los dias: ..............................<br>
                <em>Observacion: El Empleador podra hacer ajustes o cambios de horario cuando lo estime
                conveniente, no pudiendo considerarse esta modificacion como alteracion de los terminos
                del presente contrato, aceptando el trabajador la variacion del horario de trabajo,
                que hace a su cargo.</em>
            </div>
        </div>
    </div>

    {{-- SEXTA --}}
    <div class="clause">
        <span class="clause-num">SEXTA:</span> <span class="clause-label">PERIODO ORDINARIO DE PAGO (Sueldo o, Salario)</span><br>
        <div class="sub-item">
            a)&nbsp;
            @if ($contract->salary_type === 'jornal')
                Jornal
            @else
                Mensual
            @endif
            &nbsp;&nbsp;&nbsp;&nbsp;<strong>SI</strong>
            &nbsp;&nbsp;&nbsp;&nbsp;Fecha: <strong>DEL 01 AL 10 DE CADA MES</strong>
        </div>
    </div>

    {{-- SEPTIMA --}}
    <div class="clause">
        <span class="clause-num">SEPTIMA:</span>&nbsp;&nbsp;I) <span class="clause-label">MATERIALES Y HERRAMIENTAS PROPORCIONADAS POR EL EMPLEADOR</span><br>
        <div class="sub-item">
            A) Cantidad: <strong>Necesarias</strong>&nbsp;&nbsp;&nbsp;
            B) Calidad: <strong>En buen estado para la realizacion del trabajo</strong><br>
            C) Estado y condiciones de entrega: <strong>OPTIMAS</strong><br>
            Observaciones: ............................................................................................................................
        </div>
        <div class="sub-item" style="margin-top: 4px;">
            II) Las herramientas, equipos de proteccion (EPIS), implementos y demas enseres
            para la ejecucion de los trabajos correran por cuenta del empleador.
        </div>
    </div>

    {{-- OCTAVA --}}
    <div class="clause">
        <span class="clause-num">OCTAVA:</span><br>
        <div class="sub-item">
            a)&nbsp;FECHA DE INGRESO DEL TRABAJADOR:&nbsp;
            <strong>
                {{ strtoupper(
                    $contract->start_date->format('d') . ' DE ' .
                    $contract->start_date->translatedFormat('F') . ' DE ' .
                    $contract->start_date->format('Y')
                ) }}
            </strong><br>
            b)&nbsp;FECHA DE INICIO DE LABOR&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;
            <strong>
                {{ strtoupper(
                    $contract->start_date->format('d') . ' DE ' .
                    $contract->start_date->translatedFormat('F') . ' DE ' .
                    $contract->start_date->format('Y')
                ) }}
            </strong>
        </div>
    </div>

    {{-- NOVENA --}}
    @if ($contract->trial_days)
    <div class="clause">
        <span class="clause-num">NOVENA:</span>&nbsp;
        Se pacta entre las partes
        <strong>UN PERIODO PROBATORIO DE {{ $contract->trial_days }} ({{ strtoupper($trialDaysInWords) }}) DIAS</strong>
        en virtud a lo establecido en el articulo 58 del C.T. debido a la naturaleza del Trabajo
        dejandose por aclarado que, durante el periodo probatorio, cualquiera de las partes
        podra dar por terminado el Contrato de Trabajo, sin incurrir en responsabilidad alguna.
        (Art.60 C.T.).
    </div>
    @endif

    {{-- DECIMA --}}
    <div class="clause">
        <span class="clause-num">DECIMA:</span>&nbsp;
        El trabajador Contratado debera acatar las directrices emanadas de los directivos de la empresa,
        asi como a cumplir con las reglas establecidas en el Reglamento Interno y el Codigo del Trabajo
        para un mejor desempeno de sus labores.
    </div>

    {{-- UNDECIMA --}}
    <div class="clause">
        <span class="clause-num">UNDECIMA:</span>&nbsp;
        El trabajador se obliga a trabajar en forma exclusiva para el empleador y a cumplir sus directrices
        y las directrices emanadas de los representantes legales y/o jefes y encargados.
    </div>

    {{-- DUODECIMA --}}
    <div class="clause">
        <span class="clause-num">DUODECIMA:</span>&nbsp;
        El trabajador se obliga a mantener estricta reserva sobre los datos de clientes y datos
        confidenciales de la empresa ante otras empresas de la competencia o terceros particulares,
        entendiendo de este modo que podra responder penalmente por el incumplimiento de esta clausula
        por lesion de confianza.
    </div>

    {{-- DECIMO TERCERO --}}
    <div class="clause">
        <span class="clause-num">DECIMO TERCERO:</span>&nbsp;
        <span class="clause-label">BUENA FE.</span>
        El trabajador se compromete a poner a disposicion del empleador toda su capacidad y lealtad,
        obligandose siempre y en todos los casos a obrar de buena fe. Asimismo, se compromete a
        observar las politicas y normas que disponga la empleadora, teniendo como objetivo su progreso
        y permanente desarrollo.
    </div>

    {{-- DECIMO CUARTA --}}
    <div class="clause">
        <span class="clause-num">DECIMO CUARTA:</span>&nbsp;
        <span class="clause-label">CLAUSULAS ESPECIALES.</span><br>
        <div class="sub-item">
            13.1.- El trabajador se compromete a comunicar al empleador, por escrito,
            el cambio o traslado de su domicilio. Mientras esta comunicacion no se registre seran
            validas todas las comunicaciones dirigidas al ultimo domicilio denunciado, con todos
            los efectos legales. Se acompana e integra este contrato como ANEXO el croquis
            elaborado y suscripto por El trabajador.<br><br>

            13.2.- El trabajador se obliga a cumplir las normas disciplinarias, los horarios
            de trabajo y cuidar su imagen y presentacion en el trabajo y/o usar el uniforme
            establecido por La Empleadora.<br><br>

            13.3.- El trabajador debe ser cortes con los clientes no pudiendo faltar al
            respeto ni discutir con estos, sus superiores y companeros de trabajo, debiendo en
            todo momento guardar compostura y buen caracter y otorgar una atencion deferente y
            personalizada a las personas que utilicen los servicios de La Empleadora.<br><br>

            13.4.- El trabajador no podra utilizar el telefono, fax, computadoras de la
            empresa, internet, correo electronico, etc. para su uso personal. Cualquier violacion
            a esta obligacion sera considerada especialmente grave y podra ser causal de despido
            con justa causa.<br><br>

            13.5.- Conservar en buen estado los camiones, las maquinas, instrumentos,
            utiles y demas herramientas de trabajo entregados por El Empleador para realizar sus
            funciones, y comunicar oportunamente todo dano sufrido por estos, debiendo indicar,
            asimismo, al Empleador cuales utiles deben ser adquiridos o reemplazados, ya sea por
            insuficiencia o por el deterioro de los mismos.<br><br>

            13.6.- El trabajador se obliga a recibir con su firma completa habitual todas
            las comunicaciones escritas de la empresa. El incumplimiento de esta obligacion sera
            considerado falta grave.<br><br>

            13.7.- El trabajador de conformidad al art. 65 inc. m) del C.T., se obliga a
            dar aviso al Empleador de la causa de inasistencia al trabajo, y de acreditarlo
            debidamente con la documentacion pertinente, en su caso.
        </div>
    </div>

    {{-- DECIMO QUINTA --}}
    <div class="clause">
        <span class="clause-num">DECIMO QUINTA:</span>
        <span class="clause-label">BENEFICIOS:</span>
        Cuando este a cargo del empleador: Valuacion del dinero. (Art. 48 Inc. 1)<br>
        <div class="sub-item">
            Alimentacion: &nbsp;Gs..........................................<br>
            Habitacion: &nbsp;&nbsp;&nbsp;Gs..........................................<br>
            Uniforme: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Gs..........................................
        </div>
    </div>

    {{-- DECIMO SEXTA --}}
    <div class="clause">
        <span class="clause-num">DECIMO SEXTA:</span>&nbsp;
        Las partes constituyen domicilio en los indicados al inicio del presente contrato,
        donde tendran validez todas las notificaciones judiciales o extrajudiciales que
        se practicaren.
    </div>

    <br>

    <p style="text-align: justify; font-size: 11px;">
        <strong>JURISDICCION:</strong> Para los efectos de este contrato las partes se someten
        a las disposiciones del C.T., a las autoridades administrativas y jueces competentes
        de esta jurisdiccion y constituyen su domicilio en la ciudad de
        <strong>{{ strtoupper($city ?: 'ASUNCION') }}</strong>.
    </p>

    <br>

    <p style="text-align: justify; font-size: 11px;">
        Se adjunta al presente contrato croquis de ubicacion del domicilio del trabajador.
    </p>

    <br>

    <p style="text-align: justify; font-size: 11px;">
        En prueba de conformidad y aceptacion, previa lectura y ratificacion, en el lugar y
        fecha indicados, firman ambas partes, en tres ejemplares de un mismo tenor y a un solo
        efecto, quedando uno en poder de cada parte y el tercero para la Autoridad
        Administrativa del Trabajo, si lo exigiere.
    </p>

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
