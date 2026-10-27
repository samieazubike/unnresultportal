<?php

namespace Database\Factories;

use App\Enums\RegistrationSource;
use App\Models\Course;
use App\Models\Registration;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_id' => Course::factory(),
            'session' => '2025/2026',
            'semester' => fake()->randomElement([1, 2]),
            'source' => RegistrationSource::Student,
        ];
    }

    /**
     * Created by an administrator's course offering rather than the student.
     */
    public function fromOffering(): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => RegistrationSource::Offering,
        ]);
    }

    public function forSession(string $session, int $semester): static
    {
        return $this->state(fn (array $attributes) => [
            'session' => $session,
            'semester' => $semester,
        ]);
    }
}
