<?php

namespace Database\Factories;

use App\Enums\IssueReport\IssueReportStatusEnum;
use App\Models\IssueReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IssueReport>
 */
class IssueReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'message' => fake()->paragraph(),
            'status' => IssueReportStatusEnum::NEW,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IssueReportStatusEnum::RESOLVED,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }
}
