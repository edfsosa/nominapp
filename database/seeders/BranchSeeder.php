<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();
        $companyId = DB::table('companies')->value('id');

        $branches = [
            [
                'company_id' => $companyId,
                'name' => 'Sucursal Central',
                'phone' => '(021) 555-1000',
                'email' => 'central@empresa.com',
                'address' => 'Av. Principal 123',
                'city' => 'Asunción',
                'coordinates' => json_encode(['lat' => -25.263739, 'lng' => -57.575926]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Sucursal Este',
                'phone' => '(021) 555-2000',
                'email' => 'este@empresa.com',
                'address' => 'Ruta Mcal. Estigarribia Km 10',
                'city' => 'San Lorenzo',
                'coordinates' => json_encode(['lat' => -25.340098, 'lng' => -57.505191]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Sucursal Norte',
                'phone' => '(021) 555-3000',
                'email' => 'norte@empresa.com',
                'address' => 'Calle 14 de Mayo 450',
                'city' => 'Luque',
                'coordinates' => json_encode(['lat' => -25.267879, 'lng' => -57.493317]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Sucursal Sur',
                'phone' => '(021) 555-4000',
                'email' => 'sur@empresa.com',
                'address' => 'Av. Ñu Guasu 789',
                'city' => 'Fernando de la Mora',
                'coordinates' => json_encode(['lat' => -25.322377, 'lng' => -57.567980]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('branches')->insert($branches);
    }
}
