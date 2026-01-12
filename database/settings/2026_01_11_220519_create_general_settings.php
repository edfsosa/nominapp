<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->migrator->add('general.company_name', 'Mi Empresa');
        $this->migrator->add('general.company_logo', null);
        $this->migrator->add('general.company_address', null);
        $this->migrator->add('general.company_phone', null);
        $this->migrator->add('general.company_email', null);
        $this->migrator->add('general.company_ruc', null);
        $this->migrator->add('general.timezone', 'America/Asuncion');
        $this->migrator->add('general.working_hours_per_week', 48);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->migrator->delete('general.company_name');
        $this->migrator->delete('general.company_logo');
        $this->migrator->delete('general.company_address');
        $this->migrator->delete('general.company_phone');
        $this->migrator->delete('general.company_email');
        $this->migrator->delete('general.company_ruc');
        $this->migrator->delete('general.timezone');
        $this->migrator->delete('general.working_hours_per_week');
    }
};
