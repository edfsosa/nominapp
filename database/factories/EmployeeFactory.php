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
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $payrollTypes = ['monthly', 'biweekly', 'weekly'];
        $employmentTypes = ['full_time', 'day_laborer'];
        $paymentMethods = ['debit', 'cash', 'check'];
        $statuses = ['active', 'inactive', 'suspended'];
        $positions = Position::pluck('id')->toArray();
        $branches = Branch::pluck('id')->toArray();
        $schedules = Schedule::pluck('id')->toArray();

        $employmentType = $this->faker->randomElement($employmentTypes);

        return [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'ci' => $this->faker->unique()->numerify('########'),
            'birth_date' => $this->faker->date(),
            'phone' => $this->faker->phoneNumber,
            'email' => $this->faker->unique()->safeEmail,
            'hire_date' => $this->faker->date(),
            'payroll_type' => $this->faker->randomElement($payrollTypes),
            'employment_type' => $employmentType,
            'base_salary' => $employmentType === 'full_time'
                ? $this->faker->randomFloat(2, 1000000, 5000000)
                : null,
            'daily_rate' => $employmentType === 'day_laborer'
                ? $this->faker->randomFloat(2, 100000, 200000)
                : null,
            'payment_method' => $this->faker->randomElement($paymentMethods),
            'position_id' => $this->faker->randomElement($positions) ?: null,
            'branch_id' => $this->faker->randomElement($branches) ?: null,
            'schedule_id' => $this->faker->randomElement($schedules) ?: null,
            'status' => $this->faker->randomElement($statuses),
            'face_descriptor' => null,
        ];
    }
}
