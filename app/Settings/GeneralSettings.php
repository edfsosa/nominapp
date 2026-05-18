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

    public int $contract_alert_days;

    public int $face_enrollment_expiry_hours;

    public static function group(): string
    {
        return 'general';
    }
}
