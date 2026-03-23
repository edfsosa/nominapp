<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Siembra una empresa demo con todos los campos de la tabla companies. */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('companies')->insert([
            'name'            => 'Empresa Demo S.A.',
            'trade_name'      => 'Empresa Demo',
            'legal_type'      => 'S.A.',
            'founded_at'      => '2010-01-01',
            'legal_rep_name'  => 'Juan Pérez',
            'legal_rep_ci'    => '1234567',
            'ruc'             => '80012345-6',
            'employer_number' => '12345',
            'logo'            => null,
            'address'         => 'Av. Mariscal López 1234',
            'phone'           => '0211234567',
            'email'           => 'info@empresademo.com.py',
            'city'            => 'Asunción',
            'is_active'       => true,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }
}
