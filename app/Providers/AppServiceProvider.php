<?php

namespace App\Providers;

use App\Models\AttendanceDay;
use App\Observers\AttendanceDayObserver;
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
    }
}
