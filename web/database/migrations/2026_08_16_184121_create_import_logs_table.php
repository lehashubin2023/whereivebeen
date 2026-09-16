<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('game_session_id')
                ->nullable()
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('import_batch_id')
                ->nullable()
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->enum('status', ['completed', 'failed', 'in_process', 'new'])
                ->default('new');
            $table->enum('outcome', ['created', 'replaced'])
                ->nullable();
            $table->decimal('execution_time', 8, 2)
                ->unsigned()
                ->default(0);
            $table->string('error_code', 64)
                ->nullable();
            $table->json('error_context')
                ->nullable();
            $table->json('warnings')
                ->nullable();
            $table->text('error_message')
                ->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
