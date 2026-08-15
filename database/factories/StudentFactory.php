<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $entryYear = fake()->numberBetween(2020, 2025);

        return [
            'user_id' => User::factory()->role(UserRole::Student),
            'name' => fake()->name(),
            'reg_number' => sprintf('%d/%s', $entryYear, fake()->unique()->numerify('######')),
            'dept_id' => Department::factory(),
            'entry_year' => $entryYear,
        ];
    }

    /**
     * A roster row an administrator has recorded but no one has claimed yet.
     */
    public function unclaimed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
        ]);
    }
}
