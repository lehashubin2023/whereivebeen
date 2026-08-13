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
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->integer('game_session_id')
                ->unsigned();
            $table->dateTime('session_start_at');
            $table->mediumInteger('version')
                ->unsigned();
            $table->string('realm');
            $table->string('character');
            $table->enum('import_status', ['completed', 'failed', 'in_process', 'new'])
                ->default('new');
            $table->decimal('execution_time', 4, 2)
                ->unsigned()
                ->default(0);
            $table->text('import_error_message')
                ->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'game_session_id']);
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
