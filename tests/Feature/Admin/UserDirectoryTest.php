<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('opens on the students tab with the roster', function () {
    User::factory()->student()->count(2)->create();
    User::factory()->lecturer()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.users'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users')
            ->where('activeRole', 'student')
            ->has('users.data', 2)
            ->where('users.data.0.role', 'student')
            ->where('users.data.1.role', 'student')
            ->has('students')
        );
});

it('lists only the chosen role and skips the roster', function () {
    User::factory()->student()->create();
    $lecturer = User::factory()->lecturer()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.users', ['role' => 'lecturer']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeRole', 'lecturer')
            ->has('users.data', 1)
            ->where('users.data.0.id', $lecturer->id)
            ->has('students', 0)
        );
});

it('falls back to students for an unknown role', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.users', ['role' => 'janitor']))
        ->assertInertia(fn (Assert $page) => $page->where('activeRole', 'student'));
});

it('counts the accounts in every role', function () {
    User::factory()->student()->count(3)->create();
    User::factory()->lecturer()->count(2)->create();
    User::factory()->examOfficer()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.users'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('roles', [
                ['value' => 'student', 'label' => 'Student', 'count' => 3],
                ['value' => 'lecturer', 'label' => 'Lecturer', 'count' => 2],
                ['value' => 'exam_officer', 'label' => 'Examination Officer', 'count' => 1],
                ['value' => 'ict_director', 'label' => 'ICT Director', 'count' => 0],
                ['value' => 'admin', 'label' => 'Administrator', 'count' => 1],
            ])
        );
});

it('keeps the role in the pagination links', function () {
    User::factory()->lecturer()->count(26)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.users', ['role' => 'lecturer']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.last_page', 2)
            ->where('users.next_page_url', fn (string $url) => str_contains($url, 'role=lecturer'))
        );
});
