<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\LegajoPDFGenerator;
use Illuminate\Http\Response;

/**
 * Controlador de acciones web para el modelo Employee.
 */
class EmployeeController extends Controller
{
    /**
     * Genera y descarga el legajo del empleado en formato PDF.
     *
     * @param  Employee  $employee
     * @return Response
     */
    public function legajo(Employee $employee): Response
    {
        return app(LegajoPDFGenerator::class)->download($employee);
    }
}
