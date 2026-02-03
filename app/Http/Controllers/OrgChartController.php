<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Position;
use App\Settings\GeneralSettings;
use Barryvdh\DomPDF\Facade\Pdf;

class OrgChartController extends Controller
{
    /**
     * Muestra el organigrama de una empresa.
     */
    public function show(Company $company)
    {
        $orgData = $this->buildOrgChartData($company);

        return view('org-chart.show', [
            'company' => $company,
            'orgData' => $orgData,
        ]);
    }

    /**
     * Exporta el organigrama a PDF.
     */
    public function exportPdf(Company $company)
    {
        $settings = app(GeneralSettings::class);
        $orgData = $this->buildOrgChartData($company);

        // Obtener logo
        $logoPath = $company->logo ?? $settings->company_logo;
        $companyLogo = $logoPath ? storage_path('app/public/' . $logoPath) : null;
        $companyLogo = $companyLogo && file_exists($companyLogo) ? $companyLogo : null;

        $pdf = Pdf::loadView('org-chart.pdf', [
            'company' => $company,
            'orgData' => $orgData,
            'companyLogo' => $companyLogo,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("organigrama-{$company->ruc}.pdf");
    }

    /**
     * Construye la estructura de datos del organigrama como árbol jerárquico.
     */
    protected function buildOrgChartData(Company $company): array
    {
        // Obtener todos los empleados activos de la empresa con sus relaciones
        $employees = $company->employees()
            ->with(['position.department', 'position.parent'])
            ->where('status', 'active')
            ->get();

        // Obtener IDs de cargos que tienen empleados en esta empresa
        $positionIdsWithEmployees = $employees->pluck('position_id')->unique()->filter()->toArray();

        // Obtener todos los cargos relevantes (los que tienen empleados + sus ancestros)
        $relevantPositionIds = $this->getRelevantPositionIds($positionIdsWithEmployees);

        // Obtener los cargos con sus relaciones
        $positions = Position::with(['parent', 'children', 'department'])
            ->whereIn('id', $relevantPositionIds)
            ->get()
            ->keyBy('id');

        // Agrupar empleados por cargo
        $employeesByPosition = [];
        foreach ($employees as $employee) {
            $posId = $employee->position_id ?? 0;
            if (!isset($employeesByPosition[$posId])) {
                $employeesByPosition[$posId] = [];
            }
            $employeesByPosition[$posId][] = [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'photo' => $employee->photo ? asset('storage/' . $employee->photo) : null,
            ];
        }

        // Construir el árbol jerárquico
        $tree = $this->buildPositionTree($positions, $employeesByPosition);

        // Empleados sin cargo
        $unassigned = $employeesByPosition[0] ?? [];

        return [
            'tree' => $tree,
            'unassigned' => $unassigned,
        ];
    }

    /**
     * Obtiene todos los IDs de cargos relevantes (con empleados + ancestros).
     */
    protected function getRelevantPositionIds(array $positionIds): array
    {
        $allIds = $positionIds;

        // Agregar todos los ancestros de cada cargo
        foreach ($positionIds as $posId) {
            $position = Position::find($posId);
            while ($position && $position->parent_id) {
                if (!in_array($position->parent_id, $allIds)) {
                    $allIds[] = $position->parent_id;
                }
                $position = $position->parent;
            }
        }

        return array_unique($allIds);
    }

    /**
     * Construye el árbol de cargos de forma recursiva.
     */
    protected function buildPositionTree($positions, array $employeesByPosition, ?int $parentId = null): array
    {
        $tree = [];

        foreach ($positions as $position) {
            if ($position->parent_id === $parentId) {
                $node = [
                    'id' => $position->id,
                    'name' => $position->name,
                    'department' => $position->department?->name ?? 'Sin Departamento',
                    'employees' => $employeesByPosition[$position->id] ?? [],
                    'children' => $this->buildPositionTree($positions, $employeesByPosition, $position->id),
                ];
                $tree[] = $node;
            }
        }

        // Ordenar por nombre
        usort($tree, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $tree;
    }
}
