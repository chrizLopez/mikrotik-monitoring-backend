<?php

namespace Database\Factories;

use App\Models\Isp;
use App\Models\IspSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

class IspSnapshotFactory extends Factory
{
    protected $model = IspSnapshot::class;

    public function definition(): array
    {
        return [
            'isp_id' => Isp::factory(),
            'rx_bps' => fake()->numberBetween(1000, 1000000),
            'tx_bps' => fake()->numberBetween(1000, 1000000),
            'rx_bytes_total' => fake()->numberBetween(1000, 100000000),
            'tx_bytes_total' => fake()->numberBetween(1000, 100000000),
            'recorded_at' => now(),
        ];
    }
}
