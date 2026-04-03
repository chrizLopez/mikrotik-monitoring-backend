<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isp_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('isp_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('rx_bps')->nullable();
            $table->unsignedBigInteger('tx_bps')->nullable();
            $table->unsignedBigInteger('rx_bytes_total')->nullable();
            $table->unsignedBigInteger('tx_bytes_total')->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            $table->index(['isp_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isp_snapshots');
    }
};
