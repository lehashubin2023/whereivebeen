<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_map_stats', function (Blueprint $table) {
            $table->foreignId('game_session_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->unsignedSmallInteger('map_id');
            $table->unsignedInteger('seconds');
            $table->unsignedInteger('points');
            $table->unsignedInteger('deaths');

            $table->primary(['game_session_id', 'map_id']);
            $table->index(['user_id', 'map_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_map_stats');
    }
};
