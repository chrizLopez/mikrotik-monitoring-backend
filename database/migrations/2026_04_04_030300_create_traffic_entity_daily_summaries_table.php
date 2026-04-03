<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traffic_entity_daily_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('traffic_entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitored_user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('isp_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date')->index();
            $table->unsignedBigInteger('upload_bytes')->default(0);
            $table->unsignedBigInteger('download_bytes')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->timestamps();
            $table->unique(['traffic_entity_id', 'monitored_user_id', 'isp_id', 'date'], 'traffic_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_entity_daily_summaries');
    }
};
