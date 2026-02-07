<?php

namespace Database\Factories;

use App\Enums\WorkspaceStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Str;

class WorkspaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

     $name = fake()->words(rand(2, 4), true);
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(rand(10, 20)),
            'user_id' => User::factory(),
            'status' => fake()->randomElement(WorkspaceStatus::cases()),
            'extra' => [
                'color' => fake()->hexColor(),
                'icon' => fake()->randomElement(['💼', '🎯', '📊', '🚀', '💡', '🎨', '🔧', '📱']),
                'settings' => [
                    'notifications' => fake()->boolean(),
                    'public' => fake()->boolean(),
                ],
            ],
        ];
    }

    /**
     * Indicate that the workspace is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkspaceStatus::ACTIVE,
        ]);
    }

    /**
     * Indicate that the workspace is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkspaceStatus::ARCHIVED,
        ]);
    }

    /**
     * Create workspace for a specific user
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create workspace with specific name
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }
}
