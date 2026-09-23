<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_statistic_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->unsignedTinyInteger('bucket');
            $table->unsignedTinyInteger('counter');
            $table->string('entry_key', 160);
            $table->unsignedInteger('value');
            $table->json('meta')->nullable();

            $table->index(['user_id', 'bucket', 'counter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_statistic_entries');
    }
};
