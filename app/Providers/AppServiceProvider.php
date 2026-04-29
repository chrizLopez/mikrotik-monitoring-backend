<?php

namespace App\Providers;

use App\Services\Mikrotik\Contracts\MikrotikClientInterface;
use App\Services\Mikrotik\MikrotikClient;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        if (! config('dashboard.slow_query_log.enabled')) {
            return;
        }

        DB::listen(function (QueryExecuted $query): void {
            if ($query->time < config('dashboard.slow_query_log.threshold_ms', 250)) {
                return;
            }

            Log::channel(config('dashboard.slow_query_log.channel', config('logging.default')))
                ->warning('Slow dashboard query detected.', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms' => $query->time,
                ]);
        });
    }
}
