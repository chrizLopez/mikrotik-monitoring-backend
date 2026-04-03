<?php

namespace Database\Factories;

use App\Models\MonitoredUser;
use App\Models\UserSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserSnapshotFactory extends Factory
{
    protected $model = UserSnapshot::class;

    public function definition(): array
    {
        $upload = fake()->numberBetween(1000, 100000);
        $download = fake()->numberBetween(1000, 100000);

        return [
            'monitored_user_id' => MonitoredUser::factory(),
            'upload_bytes_total' => $upload,
            'download_bytes_total' => $download,
            'total_bytes' => $upload + $download,
            'max_limit' => '2M/5M',
            'state' => 'NORMAL',
            'recorded_at' => now(),
        ];
    }
}
