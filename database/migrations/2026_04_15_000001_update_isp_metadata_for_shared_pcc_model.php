<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $updates = [
            'ether1' => ['name' => 'Gomo', 'display_order' => 1, 'notes' => 'Shared PCC WAN. Gateway 192.168.254.1.'],
            'ether2' => ['name' => 'Starlink ISP New', 'display_order' => 2, 'notes' => 'Shared PCC WAN. Gateway 100.64.0.1.'],
            'ether4' => ['name' => 'Smart Bro ISP', 'display_order' => 3, 'notes' => 'Shared PCC WAN. Gateway 192.168.1.1.'],
        ];

        foreach ($updates as $interfaceName => $values) {
            DB::table('isps')
                ->where('interface_name', $interfaceName)
                ->update($values + ['updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $updates = [
            'ether1' => ['name' => 'Old Starlink', 'display_order' => 1, 'notes' => null],
            'ether2' => ['name' => 'New Starlink', 'display_order' => 2, 'notes' => null],
            'ether4' => ['name' => 'SmartBro', 'display_order' => 3, 'notes' => null],
        ];

        foreach ($updates as $interfaceName => $values) {
            DB::table('isps')
                ->where('interface_name', $interfaceName)
                ->update($values + ['updated_at' => now()]);
        }
    }
};
