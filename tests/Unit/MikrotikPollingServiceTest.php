<?php

namespace Tests\Unit;

use App\Models\MonitoredUser;
use App\Models\RouteStatusSnapshot;
use App\Models\IspHealthSnapshot;
use App\Services\Mikrotik\Contracts\MikrotikClientInterface;
use App\Services\Mikrotik\CounterDeltaCalculator;
use App\Services\Mikrotik\MikrotikNormalizer;
use App\Services\Mikrotik\MikrotikPollingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class MikrotikPollingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_skips_group_a_total_and_unknown_queues_when_polling(): void
    {
        config()->set('mikrotik.excluded_queue_names', ['GROUP_A_TOTAL']);

        $client = Mockery::mock(MikrotikClientInterface::class);
        $service = new MikrotikPollingService($client, new MikrotikNormalizer(new CounterDeltaCalculator()));
        $rawQueues = collect([
            ['name' => 'Home Router', 'bytes' => '100/200', 'max-limit' => '2M/5M'],
            ['name' => 'GROUP_A_TOTAL', 'bytes' => '200/300', 'max-limit' => '50M/50M'],
            ['name' => 'UNKNOWN_QUEUE', 'bytes' => '1/2', 'max-limit' => '1M/1M'],
        ])->keyBy('name');
        $users = collect([
            new MonitoredUser([
                'name' => 'Home Router',
                'queue_name' => 'Home Router',
                'throttled_max_limit' => '512k/2M',
                'is_active' => true,
            ]),
        ]);

        Log::spy();

        $queues = $service->mapQueues($rawQueues, $users);

        $this->assertCount(1, $queues);
        $this->assertSame('Home Router', $queues->first()->name);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_it_matches_queue_names_even_when_router_returns_extra_whitespace(): void
    {
        $client = Mockery::mock(MikrotikClientInterface::class);
        $service = new MikrotikPollingService($client, new MikrotikNormalizer(new CounterDeltaCalculator()));
        $rawQueues = collect([
            ['name' => 'VLAN20 - Camaymayan', 'bytes' => '696287/506863693', 'max-limit' => '2000000/5000000'],
        ])->keyBy('name');
        $users = collect([
            new MonitoredUser([
                'name' => 'VLAN20 - Camaymayan',
                'queue_name' => 'VLAN20 - Camaymayan',
                'throttled_max_limit' => '512k/2M',
                'is_active' => true,
            ]),
        ]);

        $queues = $service->mapQueues($rawQueues, $users);

        $this->assertCount(1, $queues);
        $this->assertSame('VLAN20 - Camaymayan', $queues->first()->name);
        $this->assertSame(696287, $queues->first()->uploadBytesTotal);
        $this->assertSame(506863693, $queues->first()->downloadBytesTotal);
    }

    public function test_it_persists_route_status_snapshots_from_interface_running_state(): void
    {
        $isp = \App\Models\Isp::factory()->create(['interface_name' => 'ether1 - Starlink']);
        $service = new MikrotikPollingService(
            Mockery::mock(MikrotikClientInterface::class),
            new MikrotikNormalizer(new CounterDeltaCalculator())
        );

        $service->persistInterfaces(collect([
            new \App\Data\Mikrotik\InterfaceStatData(
                name: 'ether1 - Starlink',
                rxBytesTotal: 1000,
                txBytesTotal: 2000,
                rxBps: 8000,
                txBps: 16000,
                online: true,
                raw: [],
            ),
        ]), CarbonImmutable::parse('2026-04-04 03:20:00'));

        $status = RouteStatusSnapshot::query()->where('isp_id', $isp->id)->latest('recorded_at')->first();

        $this->assertNotNull($status);
        $this->assertSame('online', $status->status);
        $this->assertSame(true, $status->details['running']);
    }

    public function test_it_persists_health_snapshots_from_ping_stats(): void
    {
        config()->set('mikrotik.health_targets', ['ether1 - Starlink' => '1.1.1.1']);
        config()->set('mikrotik.health_ping_count', 3);
        $isp = \App\Models\Isp::factory()->create(['interface_name' => 'ether1 - Starlink']);
        $client = Mockery::mock(MikrotikClientInterface::class);
        $client->shouldReceive('pingStats')->once()->with('1.1.1.1', 3)->andReturn([
            'latency_ms' => 32.5,
            'packet_loss_percent' => 0.0,
            'jitter_ms' => 2.5,
            'status' => 'online',
        ]);

        $service = new MikrotikPollingService($client, new MikrotikNormalizer(new CounterDeltaCalculator()));
        $service->persistHealthSnapshots(CarbonImmutable::parse('2026-04-04 03:25:00'));

        $health = IspHealthSnapshot::query()->where('isp_id', $isp->id)->latest('recorded_at')->first();

        $this->assertNotNull($health);
        $this->assertSame('online', $health->status);
        $this->assertSame(32.5, $health->latency_ms);
    }
}
