<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.contract_alert_days', 30); // 30 días antes del vencimiento por defecto
    }

    public function down(): void
    {
        $this->migrator->delete('general.contract_alert_days');
    }
};
