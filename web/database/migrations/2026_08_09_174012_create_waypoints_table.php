<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('way_points', function (Blueprint $table) {
            $table->foreignId('game_session_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->mediumInteger('sequence')
                ->unsigned();

            $table->smallInteger('map_id')
                ->unsigned()
                ->nullable();
            $table->unsignedInteger('time');
            $table->smallInteger('x')
                ->unsigned();
            $table->smallInteger('y')
                ->unsigned();

            $table->primary(['game_session_id', 'sequence']);

            $table->foreign('map_id')
                ->references('id')
                ->on('maps')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('way_points');
    }
};
