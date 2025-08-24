<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class BranchAttendanceToday extends ChartWidget
{
    protected static ?string $heading = 'Presencias / Ausencias por Sucursal';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        // Obtener la fecha actual
        $today = now()->toDateString();

        // Obtener todas las sucursales activas
        $sucursales = \App\Models\Branch::all();

        // Inicializar arreglos para los datos del gráfico
        $labels = [];
        $presentes = [];
        $ausentes = [];

        foreach ($sucursales as $sucursal) {
            // Total de empleados activos en la sucursal
            $totalEmpleados = \App\Models\Employee::where('branch_id', $sucursal->id)
                ->where('status', 'active')
                ->count();

            $empleadosPresentes = \App\Models\AttendanceDay::where('date', $today)
                ->whereHas('employee', function ($query) use ($sucursal) {
                    $query->where('branch_id', $sucursal->id);
                })
                ->count();

            $empleadosAusentes = $totalEmpleados - $empleadosPresentes;

            // Agregar datos al gráfico
            $labels[] = $sucursal->name;
            $presentes[] = $empleadosPresentes;
            $ausentes[] = $empleadosAusentes;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Presentes',
                    'data' => $presentes,
                    'backgroundColor' => 'rgba(75, 192, 192, 0.6)',
                ],
                [
                    'label' => 'Ausentes',
                    'data' => $ausentes,
                    'backgroundColor' => 'rgba(255, 99, 132, 0.6)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
