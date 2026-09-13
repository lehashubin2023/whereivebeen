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
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('filename', 255);
            $table->unsignedInteger('file_size')
                ->default(0);
            $table->enum('status', ['new', 'parsing', 'dispatched', 'failed'])
                ->default('new');
            $table->unsignedSmallInteger('sessions_found')
                ->default(0);
            $table->unsignedSmallInteger('sessions_queued')
                ->default(0);
            $table->unsignedSmallInteger('sessions_skipped')
                ->default(0);
            $table->json('skipped')
                ->nullable();
            $table->string('error_code', 64)
                ->nullable();
            $table->json('error_context')
                ->nullable();
            $table->decimal('execution_time', 8, 2)
                ->unsigned()
                ->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
