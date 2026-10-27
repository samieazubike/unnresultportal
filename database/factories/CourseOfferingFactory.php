<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseOffering>
 */
class CourseOfferingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dept_id' => Department::factory(),
            'level' => 100,
            'session' => '2025/2026',
            'semester' => 1,
            'course_id' => Course::factory(),
        ];
    }
}
