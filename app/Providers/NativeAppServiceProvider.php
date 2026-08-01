<?php

namespace App\Providers;

use App\Services\PortalConnection;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

/**
 * The desktop deliverable (README §2, §14.13), packaged from the same
 * codebase as the web application.
 *
 * Architectural note worth stating plainly: the desktop app is a shell over
 * the deployed web application, not a self-contained copy of it. The static
 * PHP binary NativePHP bundles (vendor/nativephp/php-bin) is built without
 * pdo_pgsql, so the desktop process cannot open the institutional PostgreSQL
 * database at all. Pointing the window at the web app instead means every
 * page it renders is served by the same Laravel application against the same
 * database, through the same Inertia pages — no JSON API, which README §2
 * rules out anyway.
 *
 * `database/nativephp.sqlite` is left where NativePHP puts it and serves only
 * NativePHP's own internals (queue and cache; sessions are forced to `file`).
 */
class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     */
    public function boot(): void
    {
        $portal = app(PortalConnection::class);

        $window = Window::open()
            ->title(config('app.name').' — Result Processing System')
            ->width(1440)
            ->height(900)
            ->minWidth(1024)
            ->minHeight(700)
            ->rememberState();

        if ($portal->isReachable()) {
            $window->url($portal->webUrl())->preventLeaveDomain();

            return;
        }

        // No domain lock here: the offline page is served by the local app and
        // has to be able to navigate out to the portal once it comes back.
        $window->route('desktop.offline');
    }

    /**
     * Return an array of php.ini directives to be set.
     *
     * @return array<string, string>
     */
    public function phpIni(): array
    {
        return [
            // Transcript and spreadsheet generation hold a whole document in memory.
            'memory_limit' => '512M',
            'max_execution_time' => '120',
            'upload_max_filesize' => '8M',
            'post_max_size' => '8M',
        ];
    }
}
