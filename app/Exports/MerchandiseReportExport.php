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
     */
    public function __construct(
        protected ?string $from = null,
        protected ?string $to = null,
        protected ?int $companyId = null,
        protected ?int $branchId = null,
        protected ?string $status = null,
        protected ?int $employeeId = null,
    ) {}

    /**
     * Retorna las hojas del archivo Excel.
     *
     * @return array<int, mixed>
     */
    public function sheets(): array
    {
        return [
            new MerchandiseWithdrawalsSheet($this->from, $this->to, $this->companyId, $this->branchId, $this->status, $this->employeeId),
            new MerchandiseInstallmentsSheet($this->from, $this->to, $this->companyId, $this->branchId, $this->status, $this->employeeId),
        ];
    }
}
