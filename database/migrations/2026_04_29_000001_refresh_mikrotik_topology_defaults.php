<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('isps')) {
            $isps = [
                [
                    'name' => 'Starlink',
                    'interface_name' => 'ether1 - Starlink',
                    'display_order' => 1,
                    'is_active' => true,
                ],
                [
                    'name' => 'SmartBro A',
                    'interface_name' => 'ether2 - SmartBro A',
                    'display_order' => 2,
                    'is_active' => true,
                ],
                [
                    'name' => 'SmartBro B',
                    'interface_name' => 'ether4 - SmartBro B',
                    'display_order' => 3,
                    'is_active' => true,
                ],
            ];

            foreach ($isps as $isp) {
                DB::table('isps')->updateOrInsert(
                    ['interface_name' => $isp['interface_name']],
                    $isp,
                );
            }

            DB::table('isps')
                ->whereNotIn('interface_name', collect($isps)->pluck('interface_name')->all())
                ->update(['is_active' => false]);
        }

        if (Schema::hasTable('monitored_users')) {
            $groupMap = [
                'Starlink Group' => [
                    '192.168.88.16/28',
                    '192.168.88.64/28',
                    '192.168.88.80/28',
                    '192.168.88.96/28',
                ],
                'Smart Group' => [
                    '192.168.88.112/28',
                    '192.168.88.128/28',
                    '192.168.88.144/28',
                ],
            ];

            foreach ($groupMap as $group => $subnets) {
                DB::table('monitored_users')
                    ->whereIn('subnet', $subnets)
                    ->update(['group_name' => $group]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('isps')) {
            DB::table('isps')
                ->whereIn('interface_name', [
                    'ether1 - Starlink',
                    'ether2 - SmartBro A',
                    'ether4 - SmartBro B',
                ])
                ->update(['is_active' => false]);
        }
    }
};
