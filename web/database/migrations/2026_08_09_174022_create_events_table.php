<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->foreignId('game_session_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->mediumInteger('sequence')
                ->unsigned();

            $table->unsignedTinyInteger('event_type_id')
                ->nullable();
            $table->json('payload')
                ->nullable()
                ->default(null);

            $table->primary(['game_session_id', 'sequence']);
            $table->index(['event_type_id']);

            $table->foreign('event_type_id')
                ->references('id')
                ->on('event_types')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
