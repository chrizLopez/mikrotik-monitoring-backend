<?php

namespace Database\Seeders;

use App\Models\Isp;
use Illuminate\Database\Seeder;

class IspSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            ['name' => 'Old Starlink', 'interface_name' => 'ether1', 'display_order' => 1],
            ['name' => 'New Starlink', 'interface_name' => 'ether2', 'display_order' => 2],
            ['name' => 'SmartBro', 'interface_name' => 'ether4', 'display_order' => 3],
        ])->each(function (array $isp): void {
            Isp::query()->updateOrCreate(
                ['interface_name' => $isp['interface_name']],
                $isp + ['is_active' => true],
            );
        });
    }
}
