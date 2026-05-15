<?php

namespace Database\Seeders;

use App\Models\Isp;
use Illuminate\Database\Seeder;

class IspSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            ['name' => 'Starlink', 'interface_name' => 'ether1 - Starlink', 'display_order' => 1],
            ['name' => 'SmartBro A', 'interface_name' => 'ether2 - SmartBro A', 'display_order' => 2],
            ['name' => 'Globe', 'interface_name' => 'ether4 - Globe', 'display_order' => 3],
        ])->each(function (array $isp): void {
            Isp::query()->updateOrCreate(
                ['interface_name' => $isp['interface_name']],
                $isp + ['is_active' => true],
            );
        });
    }
}
