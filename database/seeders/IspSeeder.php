<?php

namespace Database\Seeders;

use App\Models\Isp;
use Illuminate\Database\Seeder;

class IspSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            ['name' => 'Gomo', 'interface_name' => 'ether1', 'display_order' => 1, 'notes' => 'Shared PCC WAN. Gateway 192.168.254.1.'],
            ['name' => 'Starlink ISP New', 'interface_name' => 'ether2', 'display_order' => 2, 'notes' => 'Shared PCC WAN. Gateway 100.64.0.1.'],
            ['name' => 'Smart Bro ISP', 'interface_name' => 'ether4', 'display_order' => 3, 'notes' => 'Shared PCC WAN. Gateway 192.168.1.1.'],
        ])->each(function (array $isp): void {
            Isp::query()->updateOrCreate(
                ['interface_name' => $isp['interface_name']],
                $isp + ['is_active' => true],
            );
        });
    }
}
