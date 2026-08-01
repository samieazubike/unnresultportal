<?php

use App\Models\Student;
use App\Models\User;

/**
 * The public landing page. Reachable without authentication, and it must not
 * leak anything about the institution's data to a visitor.
 */
test('a guest can reach the landing page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome'));
});

test('the landing page carries no authenticated user for a guest', function () {
    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page->where('auth.user', null));
});

test('an authenticated user is still served the landing page', function () {
    $user = User::factory()->student()->create();
    Student::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->where('auth.user.id', $user->id)
        );
});

test('the landing page no longer advertises the starter kit', function () {
    $markup = file_get_contents(resource_path('js/pages/welcome.tsx'));

    expect($markup)
        ->not->toContain('laravel.com')
        ->not->toContain('Laracasts')
        ->not->toContain('Deploy now');
});

test('the brand mark does not ship an invented crest', function () {
    // The glyph is a plain academic shield. Swapping in the real crest is a
    // one-line change to CREST_SRC, and until then nothing claims to be it.
    expect(file_get_contents(resource_path('js/components/brand-mark.tsx')))
        ->toContain('const CREST_SRC: string | null = null');
});
