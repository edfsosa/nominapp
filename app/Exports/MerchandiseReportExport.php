<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Exporta el reporte de retiros de mercadería con dos hojas:
 * "Retiros" (resumen por retiro) y "Cuotas" (detalle por cuota).
 */
class MerchandiseReportExport implements WithMultipleSheets
{
    /**
     * @param  string|null  $from  Fecha inicio del filtro.
     * @param  string|null  $to  Fecha fin del filtro.
     * @param  int|null  $companyId  Filtrar por empresa.
     * @param  int|null  $branchId  Filtrar por sucursal.
     * @param  string|null  $status  Filtrar por estado del retiro.
     * @param  int|null  $employeeId  Filtrar por empleado.
     * @param  array<string>  $columns  Columnas de la hoja "Retiros" (ver MerchandiseWithdrawalsSheet::availableColumns()).
     */
    public function __construct(
        protected ?string $from = null,
        protected ?string $to = null,
        protected ?int $companyId = null,
        protected ?int $branchId = null,
        protected ?string $status = null,
        protected ?int $employeeId = null,
        protected array $columns = [],
    ) {}

    /**
     * Retorna las hojas del archivo Excel.
     * La hoja "Cuotas" siempre exporta completa; solo "Retiros" respeta la selección de columnas.
     *
     * @return array<int, mixed>
     */
    public function sheets(): array
    {
        return [
            new MerchandiseWithdrawalsSheet($this->from, $this->to, $this->companyId, $this->branchId, $this->status, $this->employeeId, $this->columns),
            new MerchandiseInstallmentsSheet($this->from, $this->to, $this->companyId, $this->branchId, $this->status, $this->employeeId),
        ];
    }
}
