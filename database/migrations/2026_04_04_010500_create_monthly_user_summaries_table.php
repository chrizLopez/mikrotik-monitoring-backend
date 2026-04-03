<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_user_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('monitored_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_cycle_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('upload_bytes')->default(0);
            $table->unsignedBigInteger('download_bytes')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->unsignedBigInteger('quota_bytes')->default(214748364800);
            $table->unsignedBigInteger('remaining_bytes')->default(214748364800);
            $table->decimal('usage_percent', 8, 2)->default(0);
            $table->string('state')->default('NORMAL')->index();
            $table->string('current_max_limit')->nullable();
            $table->timestamp('last_snapshot_at')->nullable();
            $table->timestamps();

            $table->unique(['monitored_user_id', 'billing_cycle_id']);
            $table->index(['billing_cycle_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_user_summaries');
    }
};
