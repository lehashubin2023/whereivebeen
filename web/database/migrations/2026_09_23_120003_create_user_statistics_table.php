<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_statistics', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->primary()
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->unsignedInteger('sessions_count')->default(0);
            $table->unsignedBigInteger('points_count')->default(0);
            $table->unsignedInteger('events_count')->default(0);
            $table->unsignedInteger('zones_count')->default(0);
            $table->unsignedInteger('seconds_played')->default(0);
            $table->json('groups')->nullable();
            $table->json('journey')->nullable();
            $table->boolean('is_stale')->default(true);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_statistics');
    }
};
