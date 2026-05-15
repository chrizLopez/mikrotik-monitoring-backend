<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('isps')) {
            return;
        }

        DB::table('isps')->updateOrInsert(
            ['interface_name' => 'ether4 - Globe'],
            [
                'name' => 'Globe',
                'display_order' => 3,
                'is_active' => true,
            ],
        );

        DB::table('isps')
            ->where('interface_name', 'ether4 - SmartBro B')
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('isps')) {
            return;
        }

        DB::table('isps')->updateOrInsert(
            ['interface_name' => 'ether4 - SmartBro B'],
            [
                'name' => 'SmartBro B',
                'display_order' => 3,
                'is_active' => true,
            ],
        );

        DB::table('isps')
            ->where('interface_name', 'ether4 - Globe')
            ->update(['is_active' => false]);
    }
};
