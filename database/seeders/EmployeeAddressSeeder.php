<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra direcciones postales de empleados demo.
 *
 * Requiere que ParaguayRegionsSeeder haya sido ejecutado antes
 * para que las FKs a py_cities sean válidas.
 *
 * Asigna una dirección principal a los primeros seis empleados activos.
 * Algunos también reciben una dirección laboral para mostrar el multi-tipo.
 *
 * Ciudades usadas (IDs del catálogo oficial PY):
 *   1  = Asunción
 *  87  = Fernando de la Mora
 *  92  = Lambaré
 *  94  = Luque
 *  99  = San Lorenzo
 */
class EmployeeAddressSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('status', 'active')->take(6)->get()->values();
        $now = now();

        if ($employees->isEmpty()) {
            $this->command->warn('No hay empleados activos para el EmployeeAddressSeeder.');

            return;
        }

        // Verifica que las ciudades del catálogo estén disponibles
        if (! DB::table('py_cities')->where('id', 1)->exists()) {
            $this->command->warn('La tabla py_cities está vacía. Ejecutá ParaguayRegionsSeeder primero.');

            return;
        }

        $addresses = [
            // Empleado 0 — dirección principal en Asunción
            [
                'employee_id' => $employees[0]->id,
                'type' => 'principal',
                'street' => 'Av. España 2345 casi Mcal. López',
                'neighborhood' => 'Villa Morra',
                'py_city_id' => 1,   // Asunción
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Empleado 1 — principal en San Lorenzo
            [
                'employee_id' => $employees[1]->id,
                'type' => 'principal',
                'street' => 'Calle Mcal. Estigarribia 456',
                'neighborhood' => 'Centro',
                'py_city_id' => 99,  // San Lorenzo
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Empleado 2 — principal + laboral
            [
                'employee_id' => $employees[2]->id,
                'type' => 'principal',
                'street' => 'Calle Ytororó 789',
                'neighborhood' => 'Barrio Obrero',
                'py_city_id' => 87,  // Fernando de la Mora
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'employee_id' => $employees[2]->id,
                'type' => 'laboral',
                'street' => 'Av. Principal 123',
                'neighborhood' => null,
                'py_city_id' => 1,   // Asunción (oficina central)
                'notes' => 'Dirección de la sucursal donde presta servicios',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Empleado 3 — principal en Luque
            [
                'employee_id' => $employees[3]->id,
                'type' => 'principal',
                'street' => 'Calle 14 de Mayo 1200',
                'neighborhood' => 'San Miguel',
                'py_city_id' => 94,  // Luque
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Empleado 4 — principal en Lambaré + contacto de emergencia
            [
                'employee_id' => $employees[4]->id,
                'type' => 'principal',
                'street' => 'Av. Artigas 340',
                'neighborhood' => 'Centro',
                'py_city_id' => 92,  // Lambaré
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'employee_id' => $employees[4]->id,
                'type' => 'emergencia',
                'street' => 'Calle Cerro Corá 88',
                'neighborhood' => 'Tablada Nueva',
                'py_city_id' => 92,  // Lambaré
                'notes' => 'Domicilio de familiar para contacto de emergencia',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Empleado 5 — principal en Fernando de la Mora
            [
                'employee_id' => $employees[5]->id,
                'type' => 'principal',
                'street' => 'Calle Piribebuy 560 e/ Padre Cardozo',
                'neighborhood' => 'Zona Norte',
                'py_city_id' => 87,  // Fernando de la Mora
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('employee_addresses')->insert($addresses);
    }
}
