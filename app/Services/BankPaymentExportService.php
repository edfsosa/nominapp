<?php

namespace App\Services;

use App\Models\Advance;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Genera el archivo de pagos masivos para el banco llenando el template .xlsm
 * directamente como ZIP, preservando macros, botones y controles del original.
 */
class BankPaymentExportService
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /**
     * Copia el template, inyecta los datos y retorna la ruta del archivo temporal.
     * Stampa disbursed_at en los adelantos incluidos para identificar el lote.
     *
     * @param  array<string, mixed>  $params
     * @param  Collection<int, Advance>  $advances
     */
    public function generate(array $params, Collection $advances): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'bank_export_').'.xlsm';
        copy(resource_path('excel/bank_payment_template.xlsm'), $tempFile);

        $zip = new ZipArchive;
        if ($zip->open($tempFile) !== true) {
            throw new RuntimeException('No se pudo abrir el template del banco.');
        }

        $sheetPath = $this->findSheetXmlPath($zip, 'PlanillaSalarios');
        $sheetXml = $zip->getFromName($sheetPath);

        $cellData = [
            'G3' => $params['id_empresa'],
            'G4' => $params['cuenta_debito'],
            'G5' => $params['moneda'],
        ];

        $row = 9;
        foreach ($advances as $i => $advance) {
            $cuenta = $advance->employee->bankAccounts->first()?->account_number ?? '';
            $cellData['B'.$row] = $i + 1;
            $cellData['C'.$row] = $cuenta;
            $cellData['D'.$row] = $advance->employee->ci;
            $cellData['E'.$row] = $advance->employee->full_name;
            $cellData['F'.$row] = $advance->amount;
            $cellData['G'.$row] = $params['tipo'];
            $cellData['H'.$row] = Carbon::parse($params['fecha_credito'])->format('d/m/Y');
            $row++;
        }

        $zip->addFromString($sheetPath, $this->updateSheet($sheetXml, $cellData));
        $zip->close();

        // Stampa la fecha del lote para identificar el grupo en la UI
        Advance::whereIn('id', $advances->pluck('id'))
            ->update(['disbursed_at' => $params['fecha_credito']]);

        return $tempFile;
    }

    /**
     * Genera el contenido del archivo TRANSFER.txt para enviar directamente al banco.
     * Stampa disbursed_at en los adelantos incluidos para identificar el lote.
     * El cambio de estado a 'disbursed' lo hace el usuario manualmente desde la UI.
     *
     * Formato de línea replicado de la macro GeneraTXT del template .xlsm (Itaú):
     *   D01 = registro de detalle por empleado
     *   C01 = registro de control con el total (última línea)
     *
     * @param  array<string, mixed>  $params  id_empresa, cuenta_debito, moneda, tipo, fecha_credito (Y-m-d)
     * @param  Collection<int, Advance>  $advances
     */
    public function generateTxt(array $params, Collection $advances, bool $stampDate = true, string $amountField = 'amount'): string
    {
        $lines = [];
        $totalAmount = 0.0;

        foreach ($advances as $advance) {
            $cuenta = $advance->employee->bankAccounts->first()?->account_number ?? '';
            $monto = (float) $advance->{$amountField};
            $totalAmount += $monto;

            $lines[] = $this->buildDetailLine(
                idEmpresa: $params['id_empresa'],
                cuentaDebito: $params['cuenta_debito'],
                cuentaEmpleado: $cuenta,
                tipo: $params['tipo'],
                nombre: $advance->employee->full_name,
                moneda: $params['moneda'],
                monto: $monto,
                ci: (string) $advance->employee->ci,
                fecha: $params['fecha_credito'],
            );
        }

        $lines[] = $this->buildControlLine(
            idEmpresa: $params['id_empresa'],
            cuentaDebito: $params['cuenta_debito'],
            moneda: $params['moneda'],
            totalMonto: $totalAmount,
        );

        // Solo stampa la fecha cuando se exporta directamente (flujo legacy sin lote).
        // En el flujo de DisbursementBatch, la fecha se sella al confirmar el lote.
        if ($stampDate) {
            Advance::whereIn('id', $advances->pluck('id'))
                ->update(['disbursed_at' => $params['fecha_credito']]);
        }

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * Construye una línea de detalle D01 en formato fijo Itaú.
     */
    private function buildDetailLine(
        string $idEmpresa,
        string $cuentaDebito,
        string $cuentaEmpleado,
        string $tipo,
        string $nombre,
        string $moneda,
        float $monto,
        string $ci,
        string $fecha,
    ): string {
        return 'D01'
            .$idEmpresa
            .str_pad($cuentaDebito, 10, '0', STR_PAD_LEFT)
            .'017'
            .str_pad($cuentaEmpleado, 10, '0', STR_PAD_LEFT)
            .($tipo === 'Cheque' ? 'H' : 'C')
            .str_pad(substr(strtoupper(Str::ascii($nombre)), 0, 50), 50, ' ', STR_PAD_RIGHT)
            .($moneda === 'Dólar' ? '1' : '0')
            .str_pad((string) (int) round($monto), 15, '0', STR_PAD_LEFT)
            .str_repeat('0', 15)
            .str_pad(substr(strtoupper(Str::ascii($ci)), 0, 12), 12, ' ', STR_PAD_RIGHT)
            .'0'
            .str_repeat(' ', 20)
            .'000'
            .Carbon::parse($fecha)->format('Ymd')
            .str_repeat('0', 8)
            .str_repeat(' ', 65)
            .str_repeat('0', 14)
            .str_repeat(' ', 10);
    }

    /**
     * Construye la línea de control C01 (totales) en formato fijo Itaú.
     */
    private function buildControlLine(
        string $idEmpresa,
        string $cuentaDebito,
        string $moneda,
        float $totalMonto,
    ): string {
        return 'C01'
            .$idEmpresa
            .str_pad($cuentaDebito, 10, '0', STR_PAD_LEFT)
            .'017'
            .str_repeat('0', 10)
            .'D'
            .str_repeat(' ', 50)
            .($moneda === 'Dólar' ? '1' : '0')
            .str_pad((string) (int) round($totalMonto), 15, '0', STR_PAD_LEFT)
            .str_repeat('0', 15)
            .str_repeat(' ', 12)
            .'0'
            .str_repeat(' ', 20)
            .str_repeat('0', 19)
            .str_repeat(' ', 65)
            .str_repeat('0', 14)
            .str_repeat(' ', 10);
    }

    /**
     * Resuelve la ruta XML de la hoja dentro del ZIP usando workbook.xml.rels.
     */
    private function findSheetXmlPath(ZipArchive $zip, string $sheetName): string
    {
        $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $workbook->registerXPathNamespace('x', self::NS);
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $rId = null;
        foreach ($workbook->sheets->sheet as $sheet) {
            if ((string) $sheet['name'] === $sheetName) {
                $rId = (string) $sheet->attributes('r', true)['id'];
                break;
            }
        }

        if (! $rId) {
            throw new RuntimeException("Hoja '{$sheetName}' no encontrada en el template.");
        }

        $rels = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        foreach ($rels->Relationship as $rel) {
            if ((string) $rel['Id'] === $rId) {
                $target = (string) $rel['Target'];

                return str_starts_with($target, '/xl/') ? ltrim($target, '/') : 'xl/'.$target;
            }
        }

        throw new RuntimeException("No se pudo resolver la ruta de la hoja para rId '{$rId}'.");
    }

    /**
     * Inyecta los valores de celda en el XML de la hoja usando DOM.
     *
     * @param  array<string, mixed>  $cellData  Mapa de referencia => valor.
     */
    private function updateSheet(string $xml, array $cellData): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($xml, LIBXML_NOERROR);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('x', self::NS);

        foreach ($cellData as $ref => $value) {
            $nodes = $xpath->query("//x:c[@r='{$ref}']");
            $cell = $nodes->length > 0
                ? $nodes->item(0)
                : $this->createCell($dom, $xpath, $ref);

            if ($cell instanceof DOMElement) {
                $this->setCellValue($dom, $cell, $value);
            }
        }

        return $dom->saveXML();
    }

    /**
     * Crea un nodo de celda en la fila correcta del sheetData.
     */
    private function createCell(DOMDocument $dom, DOMXPath $xpath, string $ref): ?DOMElement
    {
        preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
        $colLetter = $m[1];
        $rowNum = (int) $m[2];

        // Buscar o crear la fila
        $rows = $xpath->query("//x:sheetData/x:row[@r='{$rowNum}']");
        if ($rows->length > 0) {
            $rowNode = $rows->item(0);
        } else {
            $sheetData = $xpath->query('//x:sheetData')->item(0);
            if (! $sheetData instanceof DOMElement) {
                return null;
            }
            $rowNode = $dom->createElementNS(self::NS, 'row');
            $rowNode->setAttribute('r', $rowNum);
            $sheetData->appendChild($rowNode);
        }

        // Insertar la celda en el orden correcto de columnas
        $cell = $dom->createElementNS(self::NS, 'c');
        $cell->setAttribute('r', $ref);
        $colIdx = $this->colToIndex($colLetter);

        $inserted = false;
        foreach ($rowNode->childNodes as $sibling) {
            if (! $sibling instanceof DOMElement) {
                continue;
            }
            preg_match('/^([A-Z]+)/', (string) $sibling->getAttribute('r'), $sm);
            if (isset($sm[1]) && $this->colToIndex($sm[1]) > $colIdx) {
                $rowNode->insertBefore($cell, $sibling);
                $inserted = true;
                break;
            }
        }

        if (! $inserted) {
            $rowNode->appendChild($cell);
        }

        return $cell;
    }

    /**
     * Asigna el valor a un nodo de celda, usando inline string o número según el tipo.
     */
    private function setCellValue(DOMDocument $dom, DOMElement $cell, mixed $value): void
    {
        while ($cell->firstChild) {
            $cell->removeChild($cell->firstChild);
        }

        if (is_numeric($value)) {
            $cell->removeAttribute('t');
            $v = $dom->createElementNS(self::NS, 'v');
            $v->appendChild($dom->createTextNode((string) $value));
            $cell->appendChild($v);
        } else {
            $cell->setAttribute('t', 'inlineStr');
            $is = $dom->createElementNS(self::NS, 'is');
            $t = $dom->createElementNS(self::NS, 't');
            $t->appendChild($dom->createTextNode((string) $value));
            $is->appendChild($t);
            $cell->appendChild($is);
        }
    }

    /**
     * Convierte letra(s) de columna Excel a índice numérico (A=1, B=2, Z=26, AA=27...).
     */
    private function colToIndex(string $col): int
    {
        $idx = 0;
        foreach (str_split(strtoupper($col)) as $char) {
            $idx = $idx * 26 + (ord($char) - ord('A') + 1);
        }

        return $idx;
    }
}
