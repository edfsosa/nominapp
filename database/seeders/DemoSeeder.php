<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seeder para entorno de demostración.
 *
 * Limpia la base de datos, crea el usuario administrador y siembra
 * todos los datos de ejemplo (empresa, sucursales, empleados, nómina, etc.).
 *
 * Es seguro correrlo múltiples veces: trunca las tablas antes de insertar.
 * NO usar en producción — contiene datos ficticios.
 *
 * Uso:
 *   php artisan db:seed --class=DemoSeeder
 *
 * Variables de entorno opcionales (mismas que ProductionSeeder):
 *   ADMIN_NAME     Nombre del usuario admin     (default: "Administrador")
 *   ADMIN_EMAIL    Email del usuario admin      (default: "admin@example.com")
 *   ADMIN_PASSWORD Contraseña del usuario admin (default: "password")
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->truncateTables();
        $this->createAdminUser();

        $this->call([
            // Configuración del sistema (debe ir primero: muchos servicios leen settings al iniciar)
            SettingsSeeder::class,

            // Estructura organizacional
            CompanySeeder::class,
            ScheduleSeeder::class,
            DepartmentSeeder::class,
            BranchSeeder::class,

            // Catálogos de nómina (antes de Employee: el observer asigna deducciones obligatorias)
            DeductionSeeder::class,
            PerceptionSeeder::class,

            // Empleados y asignaciones
            EmployeeSeeder::class,
            ScheduleAssignmentSeeder::class,
            ShiftTemplateSeeder::class,
            RotationPatternSeeder::class,
            EmployeePerceptionSeeder::class,
            DocumentSeeder::class,

            // Asistencia y calendario
            HolidaySeeder::class,
            AttendanceDayWithEventsSeeder::class,
            AbsenceSeeder::class,

            // Vacaciones y permisos
            VacationSeeder::class,
            EmployeeLeaveSeeder::class,

            // Nómina y períodos
            PayrollPeriodSeeder::class,
            PayrollSeeder::class,
            LoanSeeder::class,

            // Procesos anuales e históricos
            AguinaldoSeeder::class,
            LiquidacionSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->line('<fg=green>✔</> Demo lista.</fg=green>');
        $this->command->newLine();
    }

    /** Crea el usuario administrador usando las mismas variables de entorno que ProductionSeeder. */
    private function createAdminUser(): void
    {
        $email    = env('ADMIN_EMAIL', 'admin@example.com');
        $name     = env('ADMIN_NAME', 'Administrador');
        $password = env('ADMIN_PASSWORD') ?: 'password';

        User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => $name,
                'password' => bcrypt($password),
            ]
        );

        $this->command->info("Usuario admin: $email / $password");
    }

    /**
     * Limpia todas las tablas de la aplicación antes de seedear.
     * Con FK checks desactivados, el orden no importa.
     */
    private function truncateTables(): void
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            'aguinaldo_items',
            'aguinaldos',
            'aguinaldo_periods',
            'liquidacion_items',
            'liquidaciones',
            'loan_installments',
            'loans',
            'documents',
            'employee_vacation_balances',
            'vacations',
            'employee_leaves',
            'absences',
            'attendance_events',
            'attendance_days',
            'payroll_items',
            'payrolls',
            'payroll_periods',
            'face_enrollments',
            'employee_deductions',
            'employee_perceptions',
            'shift_overrides',
            'rotation_assignments',
            'rotation_patterns',
            'shift_templates',
            'employee_schedule_assignments',
            'contracts',
            'employees',
            'schedule_breaks',
            'schedule_days',
            'schedules',
            'positions',
            'departments',
            'branches',
            'companies',
            'deductions',
            'perceptions',
            'holidays',
            'settings',
            'users',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();
    }
}
