<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('payroll.ips_deduction_code', 'IPS001');
    }

    public function down(): void
    {
        $this->migrator->delete('payroll.ips_deduction_code');
    }
};
