<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\ContractTemplate;
use Illuminate\Database\Seeder;

/** Crea un registro vacío de plantilla por cada tipo de contrato. */
class ContractTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (array_keys(Contract::getTypeOptions()) as $type) {
            ContractTemplate::firstOrCreate(['type' => $type], ['body' => null]);
        }
    }
}
