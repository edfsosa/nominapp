<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $fields = [
        'company_name',
        'company_logo',
        'company_address',
        'company_phone',
        'company_email',
        'company_ruc',
        'company_employer_number',
        'company_city',
    ];

    public function up(): void
    {
        DB::table('settings')
            ->where('group', 'general')
            ->whereIn('name', $this->fields)
            ->delete();
    }

    public function down(): void
    {
        // Fields were removed intentionally; company data is managed via the Company model.
    }
};
