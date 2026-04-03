<?php

namespace Tests\Unit;

use App\Services\Mikrotik\CounterDeltaCalculator;
use App\Services\Mikrotik\MikrotikNormalizer;
use PHPUnit\Framework\TestCase;

class MikrotikNormalizerTest extends TestCase
{
    public function test_it_normalizes_interface_stats(): void
    {
        $normalizer = new MikrotikNormalizer(new CounterDeltaCalculator());

        $data = $normalizer->normalizeInterface([
            'name' => 'ether1',
            'rx-byte' => '1000',
            'tx-byte' => '3000',
            'running' => 'true',
            'rx-bits-per-second' => '8000',
            'tx-bits-per-second' => '16000',
        ]);

        $this->assertSame('ether1', $data->name);
        $this->assertSame(1000, $data->rxBytesTotal);
        $this->assertSame(3000, $data->txBytesTotal);
        $this->assertSame(8000, $data->rxBps);
        $this->assertSame(16000, $data->txBps);
        $this->assertTrue($data->online);
    }

    public function test_it_parses_queue_bytes_safely(): void
    {
        $normalizer = new MikrotikNormalizer(new CounterDeltaCalculator());

        $queue = $normalizer->normalizeQueue([
            'name' => 'Home Router',
            'bytes' => '100/250',
            'max-limit' => '2M/5M',
        ], '512k/2M');

        $this->assertSame(100, $queue->uploadBytesTotal);
        $this->assertSame(250, $queue->downloadBytesTotal);
        $this->assertSame(350, $queue->totalBytes);
    }

    public function test_it_marks_throttled_queues_by_max_limit(): void
    {
        $normalizer = new MikrotikNormalizer(new CounterDeltaCalculator());

        $queue = $normalizer->normalizeQueue([
            'name' => 'VLAN40 - Peleyo',
            'bytes' => '100/250',
            'max-limit' => '512k / 2M',
        ], '512k/2M');

        $this->assertSame('THROTTLED', $queue->state);
    }

    public function test_it_marks_normal_queues_when_max_limit_does_not_match_throttled_limit(): void
    {
        $normalizer = new MikrotikNormalizer(new CounterDeltaCalculator());

        $queue = $normalizer->normalizeQueue([
            'name' => 'VLAN50 - Yamba',
            'bytes' => 'invalid-value',
            'max-limit' => '2M/5M',
        ], '512k/2M');

        $this->assertSame(0, $queue->uploadBytesTotal);
        $this->assertSame(0, $queue->downloadBytesTotal);
        $this->assertSame('NORMAL', $queue->state);
    }
}
