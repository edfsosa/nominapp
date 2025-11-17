<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->call(ScheduleSeeder::class);
        $this->call(DepartmentSeeder::class);
        $this->call(BranchSeeder::class);
        $this->call(EmployeeSeeder::class);
        $this->call(DeductionSeeder::class);
        $this->call(PerceptionSeeder::class);
        $this->call(HolidaySeeder::class);
        $this->call(AttendanceDayWithEventsSeeder::class);
        $this->call(PayrollPeriodSeeder::class);
    }
}
