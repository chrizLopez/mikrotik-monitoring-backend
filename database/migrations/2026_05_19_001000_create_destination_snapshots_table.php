<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destination_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('category')->index();
            $table->string('name');
            $table->unsignedInteger('visits')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->string('top_user')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            $table->index(['category', 'recorded_at']);
            $table->index(['name', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destination_snapshots');
    }
};
