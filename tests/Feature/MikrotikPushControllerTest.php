<?php

namespace Tests\Feature;

use App\Models\Isp;
use App\Models\IspHealthSnapshot;
use App\Models\IspSnapshot;
use App\Models\MonitoredUser;
use App\Models\UserSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MikrotikPushControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthorized_request_is_rejected(): void
    {
        config()->set('mikrotik.push_token', 'shared-secret');
        Log::spy();

        $this->postJson('/api/mikrotik/push', [
            'router_name' => 'MikroTik',
        ])
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized MikroTik push request.',
            ]);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'Unauthorized MikroTik push attempt.'
                && array_key_exists('ip', $context)
                && $context['router_name'] === 'MikroTik');
    }

    public function test_valid_payload_is_accepted_and_snapshots_are_created(): void
    {
        config()->set('mikrotik.push_token', 'shared-secret');

        $homeRouter = MonitoredUser::factory()->create([
            'name' => 'Home Router',
            'queue_name' => 'Home Router',
            'throttled_max_limit' => '512k/2M',
        ]);
        $peleyo = MonitoredUser::factory()->create([
            'name' => 'VLAN40 - Peleyo',
            'queue_name' => 'VLAN40 - Peleyo',
            'throttled_max_limit' => '512k/2M',
        ]);
        $ether1 = Isp::factory()->create([
            'name' => 'Starlink',
            'interface_name' => 'ether1 - Starlink',
        ]);
        $ether2 = Isp::factory()->create([
            'name' => 'SmartBro A',
            'interface_name' => 'ether2 - SmartBro A',
        ]);

        $payload = [
            'router_name' => 'MikroTik',
            'sent_at' => '2026-04-05 10:00:00',
            'queues' => [
                [
                    'name' => 'Home Router',
                    'upload_bytes' => 12345,
                    'download_bytes' => 67890,
                    'max_limit' => '2M/5M',
                ],
                [
                    'name' => 'VLAN40 - Peleyo',
                    'upload_bytes' => 123,
                    'download_bytes' => 456,
                    'max_limit' => '512k/2M',
                ],
            ],
            'interfaces' => [
                [
                    'name' => 'ether1 - Starlink',
                    'rx_bytes' => 123456789,
                    'tx_bytes' => 987654321,
                ],
                [
                    'name' => 'ether2 - SmartBro A',
                    'rx_bytes' => 5555,
                    'tx_bytes' => 6666,
                ],
            ],
        ];

        $this->withHeader('X-Mikrotik-Token', 'shared-secret')
            ->postJson('/api/mikrotik/push', $payload)
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Push data ingested',
                'queues_ingested' => 2,
                'interfaces_ingested' => 2,
                'health_ingested' => 0,
                'skipped_queues' => [],
            ]);

        $this->assertDatabaseHas('user_snapshots', [
            'monitored_user_id' => $homeRouter->id,
            'upload_bytes_total' => 12345,
            'download_bytes_total' => 67890,
            'total_bytes' => 80235,
            'max_limit' => '2M/5M',
            'state' => 'NORMAL',
        ]);
        $this->assertDatabaseHas('user_snapshots', [
            'monitored_user_id' => $peleyo->id,
            'upload_bytes_total' => 123,
            'download_bytes_total' => 456,
            'total_bytes' => 579,
            'max_limit' => '512k/2M',
            'state' => 'THROTTLED',
        ]);
        $this->assertDatabaseHas('isp_snapshots', [
            'isp_id' => $ether1->id,
            'rx_bytes_total' => 123456789,
            'tx_bytes_total' => 987654321,
        ]);
        $this->assertDatabaseHas('isp_snapshots', [
            'isp_id' => $ether2->id,
            'rx_bytes_total' => 5555,
            'tx_bytes_total' => 6666,
        ]);
    }

    public function test_group_a_total_is_skipped(): void
    {
        config()->set('mikrotik.push_token', 'shared-secret');
        MonitoredUser::factory()->create([
            'queue_name' => 'Home Router',
            'name' => 'Home Router',
        ]);

        $this->postJson('/api/mikrotik/push?token=shared-secret', [
            'queues' => [
                [
                    'name' => 'GROUP_A_TOTAL',
                    'upload_bytes' => 100,
                    'download_bytes' => 200,
                    'max_limit' => '10M/10M',
                ],
                [
                    'name' => 'Home Router',
                    'upload_bytes' => 1,
                    'download_bytes' => 2,
                    'max_limit' => '2M/5M',
                ],
            ],
        ])
            ->assertOk()
            ->assertJson([
                'queues_ingested' => 1,
                'interfaces_ingested' => 0,
                'health_ingested' => 0,
                'skipped_queues' => ['GROUP_A_TOTAL'],
            ]);

        $this->assertDatabaseCount('user_snapshots', 1);
        $this->assertDatabaseMissing('user_snapshots', [
            'max_limit' => '10M/10M',
            'total_bytes' => 300,
        ]);
    }

    public function test_unknown_queues_and_interfaces_are_logged_and_skipped(): void
    {
        config()->set('mikrotik.push_token', 'shared-secret');
        Log::spy();

        $this->withHeader('X-Mikrotik-Token', 'shared-secret')
            ->postJson('/api/mikrotik/push', [
                'router_name' => 'MikroTik',
                'queues' => [
                    [
                        'name' => 'Unknown Queue',
                        'upload_bytes' => 10,
                        'download_bytes' => 20,
                        'max_limit' => '1M/1M',
                    ],
                ],
                'interfaces' => [
                    [
                        'name' => 'ether9',
                        'rx_bytes' => 30,
                        'tx_bytes' => 40,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson([
                'queues_ingested' => 0,
                'interfaces_ingested' => 0,
                'health_ingested' => 0,
                'skipped_queues' => [],
            ]);

        $this->assertDatabaseCount('user_snapshots', 0);
        $this->assertDatabaseCount('isp_snapshots', 0);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Unknown MikroTik queue received via push ingestion.'
                && $context['queue_name'] === 'Unknown Queue'
                && $context['router_name'] === 'MikroTik');

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Unknown MikroTik interface received via push ingestion.'
                && $context['interface_name'] === 'ether9'
                && $context['router_name'] === 'MikroTik');
    }

    public function test_health_snapshots_are_created_from_push_payload(): void
    {
        config()->set('mikrotik.push_token', 'shared-secret');

        $isp = Isp::factory()->create([
            'name' => 'Starlink',
            'interface_name' => 'ether1 - Starlink',
        ]);

        $this->postJson('/api/mikrotik/push?token=shared-secret', [
            'health' => [
                [
                    'name' => 'ether1 - Starlink',
                    'ping_target' => '1.1.1.1',
                    'latency_ms' => 24.5,
                    'packet_loss_percent' => 0,
                    'jitter_ms' => 3.2,
                    'status' => 'online',
                ],
            ],
        ])
            ->assertOk()
            ->assertJson([
                'queues_ingested' => 0,
                'interfaces_ingested' => 0,
                'health_ingested' => 1,
            ]);

        $this->assertDatabaseHas('isp_health_snapshots', [
            'isp_id' => $isp->id,
            'ping_target' => '1.1.1.1',
            'status' => 'online',
        ]);

        $snapshot = IspHealthSnapshot::query()->where('isp_id', $isp->id)->first();

        $this->assertNotNull($snapshot);
        $this->assertSame(24.5, $snapshot->latency_ms);
        $this->assertSame(0.0, $snapshot->packet_loss_percent);
        $this->assertSame(3.2, $snapshot->jitter_ms);
    }

    public function test_unknown_health_interfaces_are_logged_and_skipped(): void
    {
        config()->set('mikrotik.push_token', 'shared-secret');
        Log::spy();

        $this->postJson('/api/mikrotik/push?token=shared-secret', [
            'health' => [
                [
                    'name' => 'ether9',
                    'ping_target' => '9.9.9.9',
                    'latency_ms' => 12,
                    'packet_loss_percent' => 0,
                    'jitter_ms' => 1,
                    'status' => 'online',
                ],
            ],
        ])
            ->assertOk()
            ->assertJson([
                'health_ingested' => 0,
            ]);

        $this->assertDatabaseCount('isp_health_snapshots', 0);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Unknown MikroTik health interface received via push ingestion.'
                && $context['interface_name'] === 'ether9');
    }

    public function test_throttled_state_is_derived_from_monitored_user_limit(): void
    {
        config()->set('mikrotik.push_token', 'shared-secret');

        $user = MonitoredUser::factory()->create([
            'queue_name' => 'VLAN50 - Yamba',
            'name' => 'VLAN50 - Yamba',
            'throttled_max_limit' => '512k/2M',
        ]);

        $this->postJson('/api/mikrotik/push?token=shared-secret', [
            'queues' => [
                [
                    'name' => 'VLAN50 - Yamba',
                    'upload_bytes' => 111,
                    'download_bytes' => 222,
                    'max_limit' => '512k/2M',
                ],
            ],
        ])->assertOk();

        $snapshot = UserSnapshot::query()->where('monitored_user_id', $user->id)->first();

        $this->assertNotNull($snapshot);
        $this->assertSame('THROTTLED', $snapshot->state);
    }

    public function test_interface_snapshots_are_created_from_exact_interface_mapping(): void
    {
        config()->set('mikrotik.push_token', 'shared-secret');

        $isp = Isp::factory()->create([
            'name' => 'SmartBro B',
            'interface_name' => 'ether4 - SmartBro B',
        ]);

        $this->postJson('/api/mikrotik/push?token=shared-secret', [
            'interfaces' => [
                [
                    'name' => 'ether4 - SmartBro B',
                    'rx_bytes' => 7777,
                    'tx_bytes' => 8888,
                ],
            ],
        ])->assertOk();

        $snapshot = IspSnapshot::query()->where('isp_id', $isp->id)->first();

        $this->assertNotNull($snapshot);
        $this->assertSame(7777, $snapshot->rx_bytes_total);
        $this->assertSame(8888, $snapshot->tx_bytes_total);
    }

    public function test_interface_push_derives_bps_from_previous_snapshot(): void
    {
        config()->set('mikrotik.push_token', 'shared-secret');

        $isp = Isp::factory()->create([
            'name' => 'Starlink',
            'interface_name' => 'ether1 - Starlink',
        ]);

        IspSnapshot::factory()->create([
            'isp_id' => $isp->id,
            'rx_bytes_total' => 1000,
            'tx_bytes_total' => 2000,
            'recorded_at' => '2026-04-05 10:00:00',
        ]);

        $this->postJson('/api/mikrotik/push?token=shared-secret', [
            'sent_at' => '2026-04-05 10:00:10',
            'interfaces' => [
                [
                    'name' => 'ether1 - Starlink',
                    'rx_bytes' => 2000,
                    'tx_bytes' => 3500,
                ],
            ],
        ])->assertOk();

        $snapshot = IspSnapshot::query()
            ->where('isp_id', $isp->id)
            ->latest('recorded_at')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame(800, $snapshot->rx_bps);
        $this->assertSame(1200, $snapshot->tx_bps);
    }
}
