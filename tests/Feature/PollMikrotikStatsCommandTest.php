<?php

namespace Tests\Feature;

use App\Services\Mikrotik\Exceptions\MikrotikConnectionException;
use App\Services\Mikrotik\MikrotikPollingService;
use Mockery;
use Tests\TestCase;

class PollMikrotikStatsCommandTest extends TestCase
{
    public function test_command_reports_success_when_polling_completes(): void
    {
        $service = Mockery::mock(MikrotikPollingService::class);
        $service->shouldReceive('pollAndPersist')->once();
        $this->app->instance(MikrotikPollingService::class, $service);

        $this->artisan('mikrotik:poll')
            ->expectsOutput('MikroTik poll completed successfully.')
            ->assertExitCode(0);
    }

    public function test_command_reports_failure_when_connection_fails(): void
    {
        $service = Mockery::mock(MikrotikPollingService::class);
        $service->shouldReceive('pollAndPersist')->once()->andThrow(new MikrotikConnectionException('Unable to connect to MikroTik.'));
        $this->app->instance(MikrotikPollingService::class, $service);

        $this->artisan('mikrotik:poll')
            ->expectsOutput('MikroTik poll failed: Unable to connect to MikroTik.')
            ->assertExitCode(1);
    }
}
