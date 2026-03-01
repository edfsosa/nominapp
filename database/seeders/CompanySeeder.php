<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('companies')->insert([
            'name'            => 'Empresa Demo S.A.',
            'trade_name'      => 'Empresa Demo',
            'ruc'             => '80012345-6',
            'employer_number' => 'E-12345',
            'address'         => 'Av. Mariscal López 1234',
            'phone'           => '(021) 555-0000',
            'email'           => 'info@empresademo.com.py',
            'city'            => 'Asunción',
            'is_active'       => true,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }
}
