<?php

namespace App\Providers;

use App\Services\Mikrotik\Contracts\MikrotikClientInterface;
use App\Services\Mikrotik\MikrotikClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MikrotikClientInterface::class, MikrotikClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
