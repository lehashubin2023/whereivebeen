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
        Schema::create('maps', function (Blueprint $table) {
            $table->smallInteger('id')
                ->unsigned()
                ->primary();
            // Полный набор карт WoW: имена не уникальны (несколько «Dalaran» и т.п.)
            // и могут быть длиннее 32 символов.
            $table->string('name', 128);
            $table->string('image_path', 128)
                ->nullable()
                ->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maps');
    }
};
