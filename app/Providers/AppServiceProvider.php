<?php

namespace App\Providers;

use App\Models\AttendanceDay;
use App\Models\AttendanceEvent;
use App\Observers\AttendanceDayObserver;
use App\Observers\AttendanceEventObserver;
use App\Models\Employee;
use App\Observers\EmployeeObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        AttendanceDay::observe(AttendanceDayObserver::class);
        AttendanceEvent::observe(AttendanceEventObserver::class);
        Employee::observe(EmployeeObserver::class);
    }
}
