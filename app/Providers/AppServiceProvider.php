<?php

namespace App\Providers;

use App\Models\HealthCheckup;
use App\Models\HealthPrediction;
use App\Observers\HealthCheckupObserver;
use App\Observers\HealthPredictionObserver;
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
        // 건강 위험지표 자동 평가/알림
        HealthCheckup::observe(HealthCheckupObserver::class);
        HealthPrediction::observe(HealthPredictionObserver::class);
    }
}
