<?php

namespace Database\Factories;

use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplaintMessage>
 */
class ComplaintMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'complaint_id' => Complaint::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
        ];
    }

    /**
     * Indicate the message is a system message.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
        ]);
    }
}
