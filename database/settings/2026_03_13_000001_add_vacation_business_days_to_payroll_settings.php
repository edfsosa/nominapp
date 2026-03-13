<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Días hábiles para vacaciones: 1=Lunes ... 6=Sábado (ISO week day)
        $this->migrator->add('payroll.vacation_business_days', [1, 2, 3, 4, 5, 6]);
    }

    public function down(): void
    {
        $this->migrator->delete('payroll.vacation_business_days');
    }
};
