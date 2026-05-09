<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Agrega configuraciones de préstamos, adelantos y retiro de mercaderías.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        // Préstamos (Art. 245 CLT — cuota máxima 25% del salario)
        $this->migrator->add('payroll.loan_installment_cap_percent', 25);
        $this->migrator->add('payroll.loan_max_installments', 60);
        $this->migrator->add('payroll.loan_max_interest_rate', 100);
        $this->migrator->add('payroll.loan_first_installment_days', 30);

        // Adelantos de salario
        $this->migrator->add('payroll.advance_max_percent', 50);
        $this->migrator->add('payroll.advance_max_per_period', 0);

        // Retiro de mercaderías
        $this->migrator->add('payroll.merchandise_max_amount', 10000000);
        $this->migrator->add('payroll.merchandise_max_installments', 24);
        $this->migrator->add('payroll.merchandise_first_installment_days', 30);
    }

    public function down(): void
    {
        $this->migrator->delete('payroll.loan_installment_cap_percent');
        $this->migrator->delete('payroll.loan_max_installments');
        $this->migrator->delete('payroll.loan_max_interest_rate');
        $this->migrator->delete('payroll.loan_first_installment_days');
        $this->migrator->delete('payroll.advance_max_percent');
        $this->migrator->delete('payroll.advance_max_per_period');
        $this->migrator->delete('payroll.merchandise_max_amount');
        $this->migrator->delete('payroll.merchandise_max_installments');
        $this->migrator->delete('payroll.merchandise_first_installment_days');
    }
};
