<?php

namespace Database\Factories;

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameSession>
 */
class GameSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'game_session_id' => fake()->unique()->numberBetween(1, 2_000_000_000),
            'session_start_at' => fake()->dateTimeBetween('-30 days'),
            'version' => fake()->numberBetween(1, 100),
            'realm' => fake()->randomElement(['Silvermoon', 'Ravencrest', 'Kazzak', 'Draenor', 'Argent Dawn']),
            'character' => fake()->firstName(),
        ];
    }

    /**
     * Привязать сессию к конкретному пользователю.
     */
    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }
}
