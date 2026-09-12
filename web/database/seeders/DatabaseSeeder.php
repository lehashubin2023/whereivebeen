<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(EventTypeSeeder::class);
        $this->call(MapSeeder::class);

        // User::factory(10)->create();

        User::factory()
            ->setPassword(config('admin.default_password'))
            ->create([
                'email' => config('admin.default_email'),
                'is_admin' => true,
            ]);
        User::factory()
            ->create([
                'email' => 'test@mail.ru',
                'is_admin' => false,
            ]);
    }
}
