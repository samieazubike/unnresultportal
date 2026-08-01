<?php

use App\Models\Student;
use App\Models\User;

/**
 * The dashboard route dispatches each user to the screen for their role
 * (README §1: role-specific dashboards).
 */
test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users are sent to the dashboard for their role', function (string $state, string $route) {
    $user = User::factory()->{$state}()->create();

    if ($state === 'student') {
        Student::factory()->create(['user_id' => $user->id]);
    }

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route($route));
})->with([
    'student' => ['student', 'student.dashboard'],
    'lecturer' => ['lecturer', 'lecturer.dashboard'],
    'examination officer' => ['examOfficer', 'department.dashboard'],
    'ICT director' => ['ictDirector', 'department.dashboard'],
    'administrator' => ['admin', 'admin.dashboard'],
]);

test('a role dashboard renders for the role that owns it', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('a role dashboard is refused to every other role', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
})->with(['student', 'lecturer', 'examOfficer', 'ictDirector']);
