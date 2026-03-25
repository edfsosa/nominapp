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
     * Construye la estructura de datos del organigrama agrupada por departamento.
     */
    protected function buildOrgChartData(Company $company): array
    {
        $employees = $company->employees()
            ->with(['activeContract.position.department', 'activeContract.position.parent'])
            ->where('status', 'active')
            ->get();

        $positionIdsWithEmployees = $employees->pluck('activeContract.position_id')->unique()->filter()->toArray();
        $relevantPositionIds = $this->getRelevantPositionIds($positionIdsWithEmployees);

        $positions = Position::with(['parent', 'children', 'department'])
            ->whereIn('id', $relevantPositionIds)
            ->get()
            ->keyBy('id');

        // Agrupar empleados por cargo
        $employeesByPosition = [];
        foreach ($employees as $employee) {
            $posId = $employee->activeContract?->position_id ?? 0;
            $employeesByPosition[$posId][] = [
                'id'    => $employee->id,
                'name'  => $employee->full_name,
                'photo' => $employee->photo ? asset('storage/' . $employee->photo) : null,
            ];
        }

        // Agrupar cargos por departamento y construir el árbol dentro de cada uno
        $byDepartment = $positions->groupBy('department_id');
        $tree = [];

        foreach ($byDepartment as $deptPositions) {
            $deptPositionsKeyed = $deptPositions->keyBy('id');
            $deptPositionIds    = $deptPositionsKeyed->keys()->toArray();
            $department         = $deptPositions->first()->department;

            $tree[] = [
                'name'      => $department?->name ?? 'Sin Departamento',
                'positions' => $this->buildPositionTree($deptPositionsKeyed, $deptPositionIds, $employeesByPosition),
            ];
        }

        usort($tree, fn($a, $b) => strcmp($a['name'], $b['name']));

        return [
            'tree'       => $tree,
            'unassigned' => $employeesByPosition[0] ?? [],
        ];
    }

    /**
     * Obtiene todos los IDs de cargos relevantes (con empleados + sus ancestros).
     */
    protected function getRelevantPositionIds(array $positionIds): array
    {
        $allIds = $positionIds;

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
     * Construye el árbol de cargos de forma recursiva dentro de un departamento.
     * En la llamada raíz ($parentId = null) son raíces los cargos sin padre
     * o cuyo padre pertenece a otro departamento.
     */
    protected function buildPositionTree($positions, array $deptPositionIds, array $employeesByPosition, ?int $parentId = null): array
    {
        $tree = [];

        foreach ($positions as $position) {
            $isRoot = $parentId === null
                ? (is_null($position->parent_id) || !in_array($position->parent_id, $deptPositionIds))
                : $position->parent_id === $parentId;

            if ($isRoot) {
                $tree[] = [
                    'id'         => $position->id,
                    'name'       => $position->name,
                    'department' => $position->department?->name ?? 'Sin Departamento',
                    'employees'  => $employeesByPosition[$position->id] ?? [],
                    'children'   => $this->buildPositionTree($positions, $deptPositionIds, $employeesByPosition, $position->id),
                ];
            }
        }

        usort($tree, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $tree;
    }
}
