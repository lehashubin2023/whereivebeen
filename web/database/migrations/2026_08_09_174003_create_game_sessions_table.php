<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->unsignedBigInteger('game_session_id');
            $table->unsignedBigInteger('continues_from')
                ->nullable();
            $table->dateTime('session_start_at');
            $table->dateTime('session_end_at')
                ->nullable();
            $table->mediumInteger('version')
                ->unsigned()
                ->nullable();
            $table->string('game_version', 16)
                ->nullable();
            $table->string('addon_version', 16)
                ->nullable();
            $table->unsignedTinyInteger('schema_version')
                ->default(1);
            $table->string('locale', 8)
                ->nullable();
            $table->string('faction', 16)
                ->nullable();
            $table->string('class', 16)
                ->nullable();
            $table->unsignedTinyInteger('level')
                ->nullable();
            $table->string('realm', 64);
            $table->string('character', 24);
            $table->timestamps();

            $table->unique(['user_id', 'game_session_id']);
            $table->index(['user_id', 'character', 'realm']);
            $table->index(['user_id', 'session_start_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
