<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra la cuenta bancaria principal de la empresa demo.
 *
 * Crea una cuenta corriente en Itaú marcada como principal, usada
 * para los pagos en lote de adelantos (DisbursementBatch).
 */
class CompanyBankAccountSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = DB::table('companies')->value('id');

        if (! $companyId) {
            $this->command->warn('No se encontró ninguna empresa. Ejecutá CompanySeeder primero.');

            return;
        }

        $now = now();

        DB::table('company_bank_accounts')->insert([
            [
                'company_id' => $companyId,
                'bank' => 'itau',
                'bank_company_id' => '0123456',
                'account_number' => '004-2-12345678',
                'account_type' => 'corriente',
                'holder_name' => 'Empresa Demo S.A.',
                'holder_ci' => null,
                'is_primary' => true,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => $companyId,
                'bank' => 'continental',
                'bank_company_id' => null,
                'account_number' => '102-0-98765432',
                'account_type' => 'ahorro',
                'holder_name' => 'Empresa Demo S.A.',
                'holder_ci' => null,
                'is_primary' => false,
                'status' => 'inactive',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
