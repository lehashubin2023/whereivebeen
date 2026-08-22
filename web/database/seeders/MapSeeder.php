<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class MapSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('maps:import');

        $this->command?->line(trim(Artisan::output()));
    }
}
