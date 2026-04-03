<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isp_health_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('isp_id')->constrained()->cascadeOnDelete();
            $table->string('ping_target')->nullable();
            $table->decimal('latency_ms', 8, 2)->nullable();
            $table->decimal('packet_loss_percent', 5, 2)->nullable();
            $table->decimal('jitter_ms', 8, 2)->nullable();
            $table->string('status')->default('unknown');
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isp_health_snapshots');
    }
};
