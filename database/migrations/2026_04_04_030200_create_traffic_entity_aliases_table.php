<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traffic_entity_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('traffic_entity_id')->constrained()->cascadeOnDelete();
            $table->string('alias_name')->index();
            $table->string('alias_type', 32)->index();
            $table->timestamps();
            $table->unique(['traffic_entity_id', 'alias_name', 'alias_type'], 'traffic_alias_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_entity_aliases');
    }
};
