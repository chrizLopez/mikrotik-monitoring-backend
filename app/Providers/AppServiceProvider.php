<?php

namespace App\Providers;

use App\Services\Mikrotik\Contracts\MikrotikClientInterface;
use App\Services\Mikrotik\MikrotikClient;
use App\Services\TrafficAnalytics\Contracts\AnalyticsSourceInterface;
use App\Services\TrafficAnalytics\Sources\FlowAnalyticsSource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MikrotikClientInterface::class, MikrotikClient::class);
        $this->app->bind(AnalyticsSourceInterface::class, FlowAnalyticsSource::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
