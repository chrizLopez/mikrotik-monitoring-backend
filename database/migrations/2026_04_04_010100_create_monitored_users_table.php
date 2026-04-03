<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitored_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('queue_name')->unique();
            $table->string('subnet')->nullable();
            $table->string('group_name')->nullable()->index();
            $table->unsignedBigInteger('monthly_quota_bytes')->default(214748364800);
            $table->string('normal_max_limit')->default('2M/5M');
            $table->string('throttled_max_limit')->default('512k/2M');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_users');
    }
};
