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
        Schema::create('waypoints', function (Blueprint $table) {
            $table->foreignId('game_session_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->unsignedSmallInteger('map_id')
                ->nullable();
            $table->smallInteger('sequence')
                ->unsigned();
            $table->mediumInteger('time')
                ->unsigned();
            $table->smallInteger('x')
                ->unsigned();
            $table->smallInteger('y')
                ->unsigned();
            // $table->tinyInteger('state')
            //     ->unsigned()
            //     ->default(0);

            $table->primary(['game_session_id', 'sequence']);

            $table->foreign('map_id')
                ->references('id')
                ->on('maps')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waypoints');
    }
};
