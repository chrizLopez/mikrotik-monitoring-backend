<?php

namespace Database\Factories;

use App\Models\BillingCycle;
use App\Models\MonitoredUser;
use App\Models\MonthlyUserSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonthlyUserSummaryFactory extends Factory
{
    protected $model = MonthlyUserSummary::class;

    public function definition(): array
    {
        return [
            'monitored_user_id' => MonitoredUser::factory(),
            'billing_cycle_id' => BillingCycle::factory(),
            'upload_bytes' => fake()->numberBetween(1000, 100000),
            'download_bytes' => fake()->numberBetween(1000, 100000),
            'total_bytes' => fake()->numberBetween(2000, 200000),
            'quota_bytes' => 214748364800,
            'remaining_bytes' => 214748364800,
            'usage_percent' => 0,
            'state' => 'NORMAL',
            'current_max_limit' => '2M/5M',
            'last_snapshot_at' => now(),
        ];
    }
}
