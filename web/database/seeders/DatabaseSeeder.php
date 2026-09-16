<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(EventTypeSeeder::class);
        $this->call(MapSeeder::class);

        User::factory()
            ->setPassword(config('admin.default_password'))
            ->create([
                'email' => config('admin.default_email'),
                'is_admin' => true,
            ]);
        User::factory()
            ->create([
                'email' => 'test@test.com',
                'is_admin' => false,
            ]);
    }
}
