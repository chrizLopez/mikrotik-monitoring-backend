<?php

namespace Database\Factories;

use App\Models\Isp;
use App\Models\IspHealthSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

class IspHealthSnapshotFactory extends Factory
{
    protected $model = IspHealthSnapshot::class;

    public function definition(): array
    {
        return [
            'isp_id' => Isp::factory(),
            'ping_target' => fake()->randomElement(['1.1.1.1', '8.8.8.8']),
            'latency_ms' => fake()->randomFloat(2, 12, 110),
            'packet_loss_percent' => fake()->randomElement([0, 0, 0, 5, 10]),
            'jitter_ms' => fake()->randomFloat(2, 0, 18),
            'status' => fake()->randomElement(['online', 'degraded', 'offline']),
            'recorded_at' => now(),
        ];
    }
}
