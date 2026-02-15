<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $company_name;
    public ?string $company_logo;
    public ?string $company_address;
    public ?string $company_phone;
    public ?string $company_email;
    public ?string $company_ruc;
    public ?string $company_employer_number;
    public ?string $company_city;
    public ?string $timezone;
    public int $working_hours_per_week;
    public int $max_loan_amount;
    public int $contract_alert_days;

    public static function group(): string
    {
        return 'general';
    }
}
