<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * A course is created headless by default. The head must be a member of
     * the teaching team, and the database enforces that with a deferred
     * composite foreign key, so a head can only be set in the same
     * transaction that writes the pivot row — see assignTeam() below.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_code' => strtoupper(fake()->unique()->lexify('???')).' '.fake()->unique()->numberBetween(101, 599),
            'course_title' => ucwords(fake()->words(3, true)),
            'credit_units' => fake()->randomElement([2, 3]),
            'dept_id' => Department::factory(),
            'lecturer_id' => null,
        ];
    }

    /**
     * Attach a teaching team, the first member being the head.
     *
     * @param  list<User>|null  $lecturers
     */
    public function withTeam(?array $lecturers = null, int $count = 2): static
    {
        return $this->afterCreating(function (Course $course) use ($lecturers, $count) {
            $team = $lecturers ?? User::factory()
                ->count($count)
                ->role(UserRole::Lecturer)
                ->create()
                ->all();

            self::assignTeam($course, $team, head: $team[0]);
        });
    }

    /**
     * Give the course a single lecturer, who is therefore its head.
     */
    public function headedBy(User $head): static
    {
        return $this->afterCreating(
            fn (Course $course) => self::assignTeam($course, [$head], head: $head)
        );
    }

    /**
     * Sync a course's teaching team and head atomically.
     *
     * The deferred composite foreign key on courses(id, lecturer_id) is
     * checked at commit, so writing the pivot and the head inside one
     * transaction lets them be persisted in either order.
     *
     * @param  list<User>  $lecturers
     */
    public static function assignTeam(Course $course, array $lecturers, User $head): void
    {
        DB::transaction(function () use ($course, $lecturers, $head) {
            $course->lecturers()->sync(array_map(fn (User $user) => $user->getKey(), $lecturers));

            $course->forceFill(['lecturer_id' => $head->getKey()])->save();
        });
    }
}
