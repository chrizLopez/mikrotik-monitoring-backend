<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitored_users', function (Blueprint $table): void {
            $table->index(['is_active', 'group_name'], 'monitored_users_active_group_index');
            $table->index(['is_active', 'name'], 'monitored_users_active_name_index');
        });

        Schema::table('monthly_user_summaries', function (Blueprint $table): void {
            $table->index(['billing_cycle_id', 'total_bytes'], 'monthly_user_summaries_cycle_total_index');
            $table->index(['billing_cycle_id', 'usage_percent'], 'monthly_user_summaries_cycle_usage_index');
            $table->index(['billing_cycle_id', 'last_snapshot_at'], 'monthly_user_summaries_cycle_snapshot_index');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_user_summaries', function (Blueprint $table): void {
            $table->dropIndex('monthly_user_summaries_cycle_total_index');
            $table->dropIndex('monthly_user_summaries_cycle_usage_index');
            $table->dropIndex('monthly_user_summaries_cycle_snapshot_index');
        });

        Schema::table('monitored_users', function (Blueprint $table): void {
            $table->dropIndex('monitored_users_active_group_index');
            $table->dropIndex('monitored_users_active_name_index');
        });
    }
};
