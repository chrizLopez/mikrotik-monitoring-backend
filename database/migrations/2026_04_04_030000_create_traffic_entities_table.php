<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traffic_entities', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type', 32)->index();
            $table->string('canonical_name')->unique();
            $table->string('display_name');
            $table->string('category_name')->nullable()->index();
            $table->string('vendor_name')->nullable();
            $table->string('domain')->nullable()->index();
            $table->string('app_signature')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_entities');
    }
};
