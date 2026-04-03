<?php

namespace Database\Seeders;

use App\Models\MonitoredUser;
use Illuminate\Database\Seeder;

class MonitoredUserSeeder extends Seeder
{
    public function run(): void
    {
        $quota = (int) config('dashboard.default_monthly_quota_bytes');
        $normal = config('dashboard.default_normal_max_limit');
        $throttled = config('dashboard.default_throttled_max_limit');

        collect([
            ['name' => 'Home Router', 'queue_name' => 'Home Router', 'subnet' => '192.168.88.16/28', 'group_name' => 'Group A'],
            ['name' => 'VLAN20 - Camaymayan', 'queue_name' => 'VLAN20 - Camaymayan', 'subnet' => '192.168.88.64/28', 'group_name' => 'Group A'],
            ['name' => 'VLAN30 - Rutor', 'queue_name' => 'VLAN30 - Rutor', 'subnet' => '192.168.88.80/28', 'group_name' => 'Group A'],
            ['name' => 'VLAN40 - Peleyo', 'queue_name' => 'VLAN40 - Peleyo', 'subnet' => '192.168.88.96/28', 'group_name' => 'Group B'],
            ['name' => 'VLAN50 - Yamba', 'queue_name' => 'VLAN50 - Yamba', 'subnet' => '192.168.88.112/28', 'group_name' => 'Group B'],
            ['name' => 'VLAN60 - Piso WiFi', 'queue_name' => 'VLAN60 - Piso WiFi', 'subnet' => '192.168.88.128/28', 'group_name' => 'Group B'],
            ['name' => 'VLAN70 - Olario', 'queue_name' => 'VLAN70 - Olario', 'subnet' => '192.168.88.144/28', 'group_name' => 'Group B'],
        ])->each(function (array $user) use ($quota, $normal, $throttled): void {
            MonitoredUser::query()->updateOrCreate(
                ['queue_name' => $user['queue_name']],
                $user + [
                    'monthly_quota_bytes' => $quota,
                    'normal_max_limit' => $normal,
                    'throttled_max_limit' => $throttled,
                    'is_active' => true,
                ],
            );
        });
    }
}
