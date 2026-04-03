<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_status_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('isp_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->json('details')->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            $table->index(['isp_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_status_snapshots');
    }
};
