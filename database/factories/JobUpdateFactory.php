<?php

namespace Database\Factories;

use App\Models\JobUpdate;
use App\Models\MaintenanceJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobUpdate>
 */
class JobUpdateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'maintenance_job_id' => MaintenanceJob::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
        ];
    }

    /**
     * Indicate the update is a system message.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
        ]);
    }
}
