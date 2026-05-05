<?php

namespace Database\Factories;

use App\Enums\MaintenanceJobStatus;
use App\Models\MaintenanceJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceJob>
 */
class MaintenanceJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_number' => fake()->unique()->numerify('JOB-2026-#####'),
            'complaint_id' => null,
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'scheduled_date' => now()->addDays(fake()->numberBetween(1, 14))->toDateString(),
            'status' => MaintenanceJobStatus::Assigned,
        ];
    }

    /**
     * Indicate the job is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => MaintenanceJobStatus::Completed,
            'completion_notes' => fake()->sentence(),
            'completed_at' => now(),
        ]);
    }
}
