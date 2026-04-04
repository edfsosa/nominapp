<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeder para nueva instalación de cliente.
 *
 * Solo siembra datos reales/obligatorios:
 *   - Usuario administrador (configurable vía .env)
 *   - Deducciones legalmente obligatorias (IPS)
 *
 * Es idempotente: se puede correr múltiples veces sin duplicar registros.
 * NO trunca tablas ni inserta datos demo.
 *
 * Uso:
 *   php artisan db:seed --class=ProductionSeeder
 *
 * Variables de entorno opcionales:
 *   ADMIN_NAME     Nombre del usuario admin     (default: "Administrador")
 *   ADMIN_EMAIL    Email del usuario admin      (default: "admin@example.com")
 *   ADMIN_PASSWORD Contraseña del usuario admin (default: generada aleatoriamente)
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->createAdminUser();
        $this->seedDeductions();
        $this->printChecklist();
    }

    private function createAdminUser(): void
    {
        $email    = env('ADMIN_EMAIL', 'admin@example.com');
        $name     = env('ADMIN_NAME', 'Administrador');
        $password = env('ADMIN_PASSWORD') ?: Str::random(12);

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => $name,
                'password' => bcrypt($password),
            ]
        );

        if ($user->wasRecentlyCreated) {
            $this->command->newLine();
            $this->command->info('Usuario administrador creado:');
            $this->command->line("  Nombre:     $name");
            $this->command->line("  Email:      $email");
            $this->command->line("  Contraseña: $password");
            $this->command->warn('  ¡Cambia esta contraseña inmediatamente tras el primer acceso!');
            $this->command->newLine();
        } else {
            $this->command->info("Usuario admin ya existe: $email (sin cambios).");
        }
    }

    private function seedDeductions(): void
    {
        $now = now();

        $deductions = [
            // ── Deducciones legales obligatorias ──────────────────────────────
            [
                'name'                => 'Aporte IPS',
                'code'                => 'IPS001',
                'type'                => 'legal',
                'description'         => 'Aporte al Instituto de Previsión Social (9% del salario)',
                'calculation'         => 'percentage',
                'amount'              => null,
                'percent'             => 9.00,
                'is_mandatory'        => true,
                'affects_irp'         => true,
                'apply_judicial_limit'=> false,
                'is_active'           => true,
            ],

            // ── Deducciones del sistema — Préstamos y Adelantos ───────────────
            // Usadas internamente por LoanInstallmentCalculator.
            // El monto real viene del custom_amount del EmployeeDeduction (cuota específica).
            [
                'name'                => 'Cuota de Préstamo',
                'code'                => 'PRE001',
                'type'                => 'loan',
                'description'         => 'Descuento de cuota de préstamo otorgado por el empleador. El monto se fija por cuota individual.',
                'calculation'         => 'fixed',
                'amount'              => null,
                'percent'             => null,
                'is_mandatory'        => false,
                'affects_irp'         => false,
                'apply_judicial_limit'=> false,
                'is_active'           => true,
            ],
            [
                'name'                => 'Cuota de Adelanto de Salario',
                'code'                => 'ADE001',
                'type'                => 'loan',
                'description'         => 'Descuento de adelanto de salario (hasta 50% del salario del período). El monto se fija por cuota individual.',
                'calculation'         => 'fixed',
                'amount'              => null,
                'percent'             => null,
                'is_mandatory'        => false,
                'affects_irp'         => false,
                'apply_judicial_limit'=> false,
                'is_active'           => true,
            ],
        ];

        foreach ($deductions as $deduction) {
            DB::table('deductions')->updateOrInsert(
                ['code' => $deduction['code']],
                array_merge($deduction, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        $this->command->info('Deducciones sembradas: IPS (9%), PRE001, ADE001.');
    }

    private function printChecklist(): void
    {
        $this->command->newLine();
        $this->command->line('<fg=green>✔</> Instalación base completada.</fg=green>');
        $this->command->newLine();
        $this->command->warn('Pasos pendientes para dejar el sistema operativo:');
        $this->command->newLine();
        $this->command->line('  [ ] Configuración general (Ajustes → General):');
        $this->command->line('        - Nombre, RUC, dirección y logo de la empresa');
        $this->command->line('        - Número de empleador IPS');
        $this->command->line('');
        $this->command->line('  [ ] Estructura organizacional:');
        $this->command->line('        - Crear al menos una sucursal');
        $this->command->line('        - Crear departamentos y cargos');
        $this->command->line('        - Crear horarios de trabajo');
        $this->command->line('');
        $this->command->line('  [ ] Nómina (Ajustes → Nómina):');
        $this->command->line('        - Revisar multiplicadores de horas extra');
        $this->command->line('        - Revisar parámetros de vacaciones y liquidación');
        $this->command->line('');
        $this->command->line('  [ ] Feriados nacionales (Panel → Feriados):');
        $this->command->line('        - Cargar los feriados del año en curso');
        $this->command->line('        - Repetir al inicio de cada año calendario');
        $this->command->line('');
        $this->command->line('  [ ] Catálogos opcionales:');
        $this->command->line('        - Agregar deducciones adicionales (seguros, sindicato, etc.)');
        $this->command->line('        - Agregar tipos de percepciones/bonificaciones');
        $this->command->line('');
        $this->command->line('  [ ] Cambiar la contraseña del administrador tras el primer acceso.');
        $this->command->newLine();
    }
}
