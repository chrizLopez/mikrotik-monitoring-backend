<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traffic_observations', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type', 32)->index();
            $table->foreignId('monitored_user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('isp_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('traffic_entity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('observed_name')->nullable();
            $table->string('destination_host')->nullable()->index();
            $table->string('destination_ip', 64)->nullable()->index();
            $table->string('category_name')->nullable()->index();
            $table->string('app_name')->nullable()->index();
            $table->unsignedBigInteger('upload_bytes')->default(0);
            $table->unsignedBigInteger('download_bytes')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->string('protocol')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_observations');
    }
};
