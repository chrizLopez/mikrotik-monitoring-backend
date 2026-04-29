<?php

namespace Database\Factories;

use App\Models\MonitoredUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonitoredUserFactory extends Factory
{
    protected $model = MonitoredUser::class;

    public function definition(): array
    {
        $name = fake()->unique()->name();

        return [
            'name' => $name,
            'queue_name' => $name,
            'subnet' => '192.168.88.'.fake()->numberBetween(2, 254).'/28',
            'group_name' => fake()->randomElement(['Starlink Group', 'Smart Group']),
            'monthly_quota_bytes' => 214748364800,
            'normal_max_limit' => '2M/5M',
            'throttled_max_limit' => '512k/2M',
            'is_active' => true,
        ];
    }
}
