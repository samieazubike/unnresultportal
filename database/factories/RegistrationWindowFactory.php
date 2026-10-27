<?php

namespace Database\Factories;

use App\Models\RegistrationWindow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrationWindow>
 */
class RegistrationWindowFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session' => '2025/2026',
            'semester' => 1,
            'is_open' => false,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_open' => true,
        ]);
    }
}
