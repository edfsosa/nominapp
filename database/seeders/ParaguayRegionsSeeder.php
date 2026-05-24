<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Puebla las tablas py_departments y py_cities con los datos oficiales de Paraguay. */
class ParaguayRegionsSeeder extends Seeder
{
    public function run(): void
    {
        $dataPath = database_path('data/paraguay');

        $departments = json_decode(file_get_contents("{$dataPath}/departments.json"), true);
        $cities = json_decode(file_get_contents("{$dataPath}/cities.json"), true);

        $now = now()->toDateTimeString();

        DB::table('py_departments')->insertOrIgnore(
            array_map(fn (array $d) => [
                'id' => $d['id'],
                'name' => $d['name'],
                'capital' => $d['capital'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $departments)
        );

        DB::table('py_cities')->insertOrIgnore(
            array_map(fn (array $c) => [
                'id' => $c['id'],
                'py_department_id' => $c['department_id'],
                'name' => $c['name'],
                'population' => $c['population'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $cities)
        );
    }
}
