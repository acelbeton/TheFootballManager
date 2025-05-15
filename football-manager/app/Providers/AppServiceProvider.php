<?php

namespace App\Providers;

use App\Models\Statistic;
use App\Observers\StatisticObserver;
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
        Statistic::observe(StatisticObserver::class);
    }
}
