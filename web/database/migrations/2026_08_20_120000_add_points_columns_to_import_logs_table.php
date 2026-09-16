<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->unsignedInteger('points_total')->default(0)->after('status');
            $table->unsignedInteger('points_done')->default(0)->after('points_total');
        });
    }

    public function down(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->dropColumn(['points_total', 'points_done']);
        });
    }
};
