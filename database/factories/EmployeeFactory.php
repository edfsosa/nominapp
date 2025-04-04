<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'ci' => $this->faker->unique()->numerify('########'),
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'salary' => $this->faker->numberBetween(3000000, 10000000), // 3M-10M PYG
            'department' => $this->faker->randomElement([
                'Contabilidad',
                'Ventas',
                'TI',
            ]),
            'hire_date' => $this->faker->dateTimeBetween('-5 years', 'now'),
            'status' => $this->faker->randomElement(['activo', 'inactivo']),
            'branch' => $this->faker->randomElement([
                'Asuncion',
                'Luque',
                'Capiata',
            ]),
            'contract_type' => $this->faker->randomElement(['mensualero', 'jornalero']),
        ];
    }

    // States opcionales para personalizar datos
    public function monthly(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'contract_type' => 'mensualero',
                'salary' => $this->faker->numberBetween(3000000, 10000000), // Salario mensual
            ];
        });
    }

    public function daily(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'contract_type' => 'jornalero',
                'salary' => $this->faker->numberBetween(50000, 200000), // Jornal diario
            ];
        });
    }
}
