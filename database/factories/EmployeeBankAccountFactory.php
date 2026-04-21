<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeBankAccount>
 */
class EmployeeBankAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $banks = array_keys(\App\Models\EmployeeBankAccount::BANKS);
        $types = array_keys(\App\Models\EmployeeBankAccount::ACCOUNT_TYPES);

        return [
            'bank' => $this->faker->randomElement($banks),
            'account_number' => $this->faker->numerify('##########'),
            'account_type' => $this->faker->randomElement($types),
            'holder_name' => $this->faker->name,
            'is_primary' => false,
            'status' => 'active',
        ];
    }

    /** Estado: cuenta principal activa. */
    public function primary(): static
    {
        return $this->state(['is_primary' => true, 'status' => 'active']);
    }

    /** Estado: cuenta inactiva. */
    public function inactive(): static
    {
        return $this->state(['is_primary' => false, 'status' => 'inactive']);
    }
}
