<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maps', function (Blueprint $table) {
            $table->smallInteger('id')
                ->unsigned()
                ->primary();
            $table->string('name', 128);
            $table->boolean('auto_added')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maps');
    }
};
