<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.company_employer_number', '');
        $this->migrator->add('general.company_city', '');
    }
};
