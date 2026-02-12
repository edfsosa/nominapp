<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Position;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    /** Cache de IDs para evitar queries repetidas por cada instancia */
    protected static ?array $positionIds = null;
    protected static ?array $branchIds = null;
    protected static ?array $scheduleIds = null;

    public function definition(): array
    {
        static::$positionIds ??= Position::pluck('id')->toArray();
        static::$branchIds ??= Branch::pluck('id')->toArray();
        static::$scheduleIds ??= Schedule::pluck('id')->toArray();

        $employmentType = $this->faker->randomElement(['full_time', 'day_laborer']);

        // Edad laboral realista: 18-60 años
        $birthDate = $this->faker->dateTimeBetween('-60 years', '-18 years');

        // Contratación: después de cumplir 18, hasta hoy
        $minHireDate = (clone $birthDate)->modify('+18 years');
        $hireDate = $this->faker->dateTimeBetween($minHireDate, 'now');

        // Salario mínimo vigente Paraguay
        $salarioMinimo = 2899048;

        return [
            'first_name'      => $this->faker->firstName,
            'last_name'       => $this->faker->lastName,
            'ci'              => (string) $this->faker->unique()->numberBetween(1000000, 12000000),
            'birth_date'      => $birthDate->format('Y-m-d'),
            'phone'           => $this->faker->numerify('(09##) ###-###'),
            'email'           => $this->faker->unique()->safeEmail,
            'hire_date'       => $hireDate->format('Y-m-d'),
            'payroll_type'    => $this->faker->randomElement(['monthly', 'biweekly', 'weekly']),
            'employment_type' => $employmentType,
            'base_salary'     => $employmentType === 'full_time'
                ? $this->faker->numberBetween($salarioMinimo, $salarioMinimo * 3)
                : null,
            'daily_rate'      => $employmentType === 'day_laborer'
                ? round($salarioMinimo / 30 * $this->faker->randomFloat(2, 1.0, 1.5))
                : null,
            'payment_method'  => $this->faker->randomElement(['debit', 'cash', 'check']),
            'position_id'     => !empty(static::$positionIds) ? $this->faker->randomElement(static::$positionIds) : null,
            'branch_id'       => !empty(static::$branchIds) ? $this->faker->randomElement(static::$branchIds) : null,
            'schedule_id'     => !empty(static::$scheduleIds) ? $this->faker->randomElement(static::$scheduleIds) : null,
            'status'          => $this->faker->randomElement(['active', 'inactive', 'suspended']),
            'face_descriptor' => null,
        ];
    }
}
