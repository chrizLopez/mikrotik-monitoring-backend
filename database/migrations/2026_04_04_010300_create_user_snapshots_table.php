<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('monitored_user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('upload_bytes_total')->default(0);
            $table->unsignedBigInteger('download_bytes_total')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->string('max_limit')->nullable();
            $table->string('state')->default('NORMAL')->index();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            $table->index(['monitored_user_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_snapshots');
    }
};
