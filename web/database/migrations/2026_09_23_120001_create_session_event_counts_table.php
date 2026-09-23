<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_event_counts', function (Blueprint $table) {
            $table->foreignId('game_session_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->unsignedTinyInteger('event_type_id');
            $table->unsignedInteger('total');

            $table->primary(['game_session_id', 'event_type_id']);
            $table->index(['user_id', 'event_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_event_counts');
    }
};
