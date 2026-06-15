<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

/** Plantilla de cuerpo/cláusulas por tipo de contrato, con alcance por empresa. */
class ContractTemplate extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected array $auditInclude = [
        'intro_text',
        'body',
        'closing_text',
        'signature_notes',
        'document_title',
        'document_subtitle',
        'signature_employee_label',
        'signature_employer_label',
        'signature_employer_sublabel',
        'show_header',
        'show_footer',
    ];

    protected $fillable = [
        'company_id',
        'type',
        'body',
        'intro_text',
        'closing_text',
        'signature_notes',
        'document_title',
        'document_subtitle',
        'document_art_reference',
        'signature_employee_label',
        'signature_employer_label',
        'signature_employer_sublabel',
        'show_header',
        'show_footer',
    ];

    protected function casts(): array
    {
        return [
            'show_header' => 'boolean',
            'show_footer' => 'boolean',
        ];
    }

    /**
     * Empresa a la que pertenece esta plantilla.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Retorna la plantilla para el tipo de contrato y empresa dados, o null si no existe.
     * Si no se provee companyId, retorna null.
     *
     * @param  string  $type  Tipo de contrato (ej: 'indefinido', 'plazo_fijo')
     * @param  int|null  $companyId  ID de la empresa; si es null retorna null
     */
    public static function getForType(string $type, ?int $companyId = null): ?static
    {
        if ($companyId === null) {
            return null;
        }

        return static::where('type', $type)->where('company_id', $companyId)->first();
    }

    /**
     * Catálogo de variables disponibles para usar en las secciones editables.
     * Clave = token en el template, valor = descripción legible para el usuario.
     *
     * @return array<string, string>
     */
    public static function getAvailableVariables(): array
    {
        return [
            '{ciudad}' => 'Ciudad de la empresa',
            '{dia}' => 'Día del mes de inicio (ej: 15)',
            '{mes}' => 'Nombre del mes en mayúsculas (ej: JUNIO)',
            '{año}' => 'Año en palabras (ej: DOS MIL VEINTICINCO)',
            '{representante_legal}' => 'Nombre del representante legal de la empresa',
            '{ci_representante}' => 'Cédula del representante legal',
            '{nombre_empresa}' => 'Razón social de la empresa',
            '{ruc_empresa}' => 'RUC de la empresa',
            '{domicilio_empresa}' => 'Dirección de la empresa',
            '{nombre_empleado}' => 'Nombre completo del empleado (en mayúsculas)',
            '{ci_empleado}' => 'Cédula de identidad del empleado',
            '{edad_empleado}' => 'Edad del empleado en años',
            '{sexo_empleado}' => 'Género del empleado',
            '{estado_civil_empleado}' => 'Estado civil del empleado',
            '{cargo}' => 'Nombre del cargo / puesto',
            '{nacionalidad_empleado}' => 'Nacionalidad del empleado',
            '{domicilio_empleado}' => 'Dirección del empleado',
            '{salario}' => 'Monto del salario formateado (ej: 2.500.000)',
            '{salario_en_palabras}' => 'Salario escrito en palabras',
            '{tipo_jornada}' => 'Tipo de jornada (DIURNA / NOCTURNA / MIXTA)',
            '{horas_semanales}' => 'Horas semanales (número)',
            '{horas_semanales_en_palabras}' => 'Horas semanales escritas en palabras',
            '{dias_prueba}' => 'Días de período de prueba',
            '{dias_prueba_en_palabras}' => 'Días de prueba escritos en palabras',
            '{duracion_contrato}' => 'Duración del contrato en palabras (ej: un (1) año)',
            '{tipo_contrato}' => 'Tipo de contrato (ej: Por Tiempo Indefinido)',
            '{fecha_inicio}' => 'Fecha de inicio del contrato (dd/mm/YYYY)',
            '{fecha_fin}' => 'Fecha de finalización (dd/mm/YYYY o INDEFINIDO)',
            '{modalidad}' => 'Modalidad de trabajo (Presencial / Remoto / Híbrido)',
            '{metodo_pago}' => 'Método de pago (Efectivo / Débito bancario / Cheque)',
            '{tipo_salario}' => 'Tipo de remuneración (Mensualizado / Jornalero)',
            '{departamento}' => 'Nombre del departamento del cargo',
        ];
    }

    /**
     * Texto base del párrafo introductorio con tokens de variables.
     * Equivale al intro hardcodeado en el blade, convertido a plantilla editable.
     */
    public static function getDefaultIntroText(): string
    {
        return '<p>En la ciudad de <strong>{ciudad}</strong> a los <strong>{dia}</strong> dia del mes de <strong>{mes}</strong> del año <strong>{año}</strong>, por una parte el señor/a <strong>{representante_legal}</strong>, con C.I.N.: <strong>{ci_representante}</strong>, de .......... años de edad; sexo ......................... estado civil ..............................., de profesion ..............................., de nacionalidad ............................... y con domicilio para todos sus efectos legales en la casa de las calles <strong>{domicilio_empresa}</strong>, en nombre y representacion de la firma <strong>{nombre_empresa}</strong> en su calidad de ............................... de la misma, denominado en adelante <strong>"EMPLEADOR"</strong>, y por la otra el señor/a <strong>{nombre_empleado}</strong> con C.I.N. <strong>{ci_empleado}</strong>, de <strong>{edad_empleado}</strong> años de edad; sexo <strong>{sexo_empleado}</strong> de estado civil <strong>{estado_civil_empleado}</strong>, profesion u otro oficio <strong>{cargo}</strong> nacionalidad <strong>{nacionalidad_empleado}</strong> y con domicilio en la casa de las calles <strong>{domicilio_empleado}</strong> denominada en adelante <strong>"TRABAJADOR"</strong> conviene en celebrar el presente <strong>CONTRATO INDIVIDUAL DE TRABAJO</strong> bajo las siguientes clausulas:</p>';
    }

    /**
     * Texto base del cuerpo/cláusulas con tokens de variables.
     * Incluye las cláusulas típicas de un contrato laboral paraguayo.
     */
    public static function getDefaultBodyText(): string
    {
        return '<p><strong>PRIMERA: OBJETO DEL CONTRATO</strong></p>'
            .'<p>El TRABAJADOR se compromete a desempeñar las funciones de <strong>{cargo}</strong>, con las responsabilidades inherentes a dicho cargo, bajo la dirección y dependencia del EMPLEADOR.</p>'
            .'<p><strong>SEGUNDA: LUGAR DE TRABAJO</strong></p>'
            .'<p>El TRABAJADOR prestará sus servicios en las instalaciones de <strong>{nombre_empresa}</strong>, ubicadas en <strong>{domicilio_empresa}</strong>, o en los lugares que la empresa designe.</p>'
            .'<p><strong>TERCERA: JORNADA DE TRABAJO</strong></p>'
            .'<p>La jornada de trabajo será de <strong>{horas_semanales} ({horas_semanales_en_palabras})</strong> horas semanales en horario <strong>{tipo_jornada}</strong>, conforme al horario establecido por el EMPLEADOR.</p>'
            .'<p><strong>CUARTA: REMUNERACIÓN</strong></p>'
            .'<p>El EMPLEADOR abonará al TRABAJADOR la suma de <strong>Gs. {salario} ({salario_en_palabras})</strong>, conforme a la modalidad de pago acordada entre las partes.</p>'
            .'<p><strong>QUINTA: PERÍODO DE PRUEBA</strong></p>'
            .'<p>Las partes acuerdan que los primeros <strong>{dias_prueba} ({dias_prueba_en_palabras})</strong> días de vigencia del presente contrato se consideran período de prueba, conforme al Art. 58 del Código del Trabajo.</p>'
            .'<p><strong>SEXTA: DISPOSICIONES GENERALES</strong></p>'
            .'<p>Ambas partes se someten a las disposiciones del Código Laboral de la República del Paraguay y demás leyes laborales vigentes para todo lo no previsto en el presente contrato.</p>';
    }

    /**
     * Texto base del cierre del contrato con tokens de variables.
     */
    public static function getDefaultClosingText(): string
    {
        return '<p>En prueba de conformidad con todo lo estipulado precedentemente, las partes firman el presente contrato en dos (2) ejemplares de un mismo tenor y a un solo efecto, en la ciudad de <strong>{ciudad}</strong>, a los <strong>{dia}</strong> días del mes de <strong>{mes}</strong> del año <strong>{año}</strong>.</p>';
    }

    /**
     * Texto base de las notas en la sección de firmas.
     */
    public static function getDefaultSignatureNotes(): string
    {
        return 'Firmado en dos (2) ejemplares de un mismo tenor y a un solo efecto.';
    }

    /**
     * Reemplaza los tokens {variable} en el HTML con los valores provistos.
     *
     * @param  array<string, string>  $values  Mapa de token → valor resuelto
     */
    public static function resolveVariables(string $html, array $values): string
    {
        return str_replace(array_keys($values), array_values($values), $html);
    }

    /**
     * Formatea los campos auditados para su presentación en el RelationManager de historial.
     */
    public function formatAuditFieldsForPresentation(string $column, mixed $auditRecord): HtmlString
    {
        $values = $auditRecord->{$column} ?? [];

        if (empty($values)) {
            return new HtmlString('<span class="text-gray-400 text-xs">—</span>');
        }

        $fieldLabels = [
            'intro_text' => 'Introducción',
            'body' => 'Cuerpo / Cláusulas',
            'closing_text' => 'Cierre',
            'signature_notes' => 'Notas de firma',
            'document_title' => 'Título del documento',
            'document_subtitle' => 'Subtítulo',
            'signature_employee_label' => 'Etiqueta firma empleado',
            'signature_employer_label' => 'Etiqueta firma empleador',
            'signature_employer_sublabel' => 'Subetiqueta firma empleador',
            'show_header' => 'Mostrar encabezado',
            'show_footer' => 'Mostrar pie de página',
        ];

        $html = '<ul class="space-y-0.5 text-sm">';
        foreach ($values as $key => $value) {
            $label = $fieldLabels[$key] ?? Str::headline($key);
            $formatted = $this->formatAuditValue($key, $value);
            $html .= "<li><span class=\"text-gray-500\">{$label}:</span> <span class=\"font-medium\">{$formatted}</span></li>";
        }
        $html .= '</ul>';

        return new HtmlString($html);
    }

    /**
     * Convierte el valor crudo de un campo auditado a su representación legible.
     */
    private function formatAuditValue(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($key) {
            'show_header', 'show_footer' => $value ? 'Sí' : 'No',
            'intro_text', 'body', 'closing_text', 'signature_notes' => '(texto HTML — ver plantilla)',
            default => (string) $value,
        };
    }
}
