<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('payroll.irp_annual_threshold', 80_000_000); // ~80M Gs/año (Ley 2421/04)
        $this->migrator->add('payroll.irp_rate', 10.0);                   // 10% sobre renta gravada
    }

    public function down(): void
    {
        $this->migrator->delete('payroll.irp_annual_threshold');
        $this->migrator->delete('payroll.irp_rate');
    }
};
