<?php

use App\Services\PortalConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The desktop app is a shell over the web application: it renders the portal
 * over HTTP so it reads the same PostgreSQL database, and serves a local page
 * only when that server cannot be reached.
 */
beforeEach(function () {
    config([
        'desktop.web_url' => 'http://portal.test',
        'desktop.health_path' => '/up',
        'desktop.health_timeout' => 1,
    ]);
});

test('the portal counts as reachable when its health endpoint answers', function () {
    Http::fake(['portal.test/up' => Http::response('ok')]);

    expect(app(PortalConnection::class)->isReachable())->toBeTrue();
});

test('a failing health endpoint is not reachable', function () {
    Http::fake(['portal.test/up' => Http::response('', 503)]);

    expect(app(PortalConnection::class)->isReachable())->toBeFalse();
});

test('an unreachable host is reported rather than thrown', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    expect(app(PortalConnection::class)->isReachable())->toBeFalse();
});

test('a trailing slash on the configured url is trimmed', function () {
    config(['desktop.web_url' => rtrim('http://portal.test/', '/')]);

    expect(app(PortalConnection::class)->webUrl())->toBe('http://portal.test');
});

test('the offline page names the configured portal address', function () {
    $this->get(route('desktop.offline'))
        ->assertOk()
        ->assertSee('http://portal.test')
        ->assertSee('Try again');
});

test('the offline page does not depend on the vite manifest', function () {
    // It has to render with the network down and, in a packaged build,
    // without any asset resolution succeeding.
    expect(file_get_contents(resource_path('views/desktop/offline.blade.php')))
        ->not->toContain('@vite');
});

test('retrying sends the window to the portal once it answers', function () {
    Http::fake(['portal.test/up' => Http::response('ok')]);

    $this->get(route('desktop.retry'))
        ->assertRedirect('http://portal.test');
});

test('retrying while the portal is still down returns to the offline page', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->from(route('desktop.offline'))
        ->get(route('desktop.retry'))
        ->assertRedirect(route('desktop.offline'))
        ->assertSessionHas('error');
});

test('the packaged build strips the database credentials from its env', function () {
    // The desktop process never opens the database itself, and '*_SECRET'
    // does not match DB_PASSWORD.
    expect(config('nativephp.cleanup_env_keys'))->toContain('DB_*');
});
