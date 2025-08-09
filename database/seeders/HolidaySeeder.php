<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HolidaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        // Feriados Paraguay 2025 (varios se trasladan al lunes)
        $holidays = [
            ['date' => '2025-01-01', 'name' => 'Año Nuevo'],
            ['date' => '2025-03-03', 'name' => 'Día de los Héroes'], // trasladado
            ['date' => '2025-04-17', 'name' => 'Jueves Santo'],
            ['date' => '2025-04-18', 'name' => 'Viernes Santo'],
            ['date' => '2025-05-01', 'name' => 'Día del Trabajador'],
            ['date' => '2025-05-14', 'name' => 'Independencia Nacional'],
            ['date' => '2025-06-12', 'name' => 'Paz del Chaco'],
            ['date' => '2025-08-15', 'name' => 'Fundación de Asunción'],
            ['date' => '2025-09-29', 'name' => 'Victoria de Boquerón'],
            ['date' => '2025-12-08', 'name' => 'Virgen de Caacupé'],
            ['date' => '2025-12-25', 'name' => 'Navidad'],
        ];

        DB::table('holidays')->insert(
            collect($holidays)->map(fn($h) => array_merge($h, [
                'created_at' => $now,
                'updated_at' => $now,
            ]))->toArray()
        );
    }
}
