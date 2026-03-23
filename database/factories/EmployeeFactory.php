<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    /** Cache de IDs para evitar queries repetidas por cada instancia */
    protected static ?array $branchIds = null;
    protected static ?array $scheduleIds = null;

    public function definition(): array
    {
        static::$branchIds ??= Branch::pluck('id')->toArray();
        static::$scheduleIds ??= Schedule::pluck('id')->toArray();

        // Edad laboral realista: 18-60 años
        $birthDate = $this->faker->dateTimeBetween('-60 years', '-18 years');

        return [
            'first_name'      => $this->faker->firstName,
            'last_name'       => $this->faker->lastName,
            'ci'              => (string) $this->faker->unique()->numberBetween(1000000, 12000000),
            'birth_date'      => $birthDate->format('Y-m-d'),
            'phone'           => $this->faker->numerify('09########'),
            'email'           => $this->faker->unique()->safeEmail,
            'branch_id'       => !empty(static::$branchIds) ? $this->faker->randomElement(static::$branchIds) : null,
            'schedule_id'     => !empty(static::$scheduleIds) ? $this->faker->randomElement(static::$scheduleIds) : null,
            'status'          => $this->faker->randomElement(['active', 'inactive', 'suspended']),
            'face_descriptor' => null,
        ];
    }
}
