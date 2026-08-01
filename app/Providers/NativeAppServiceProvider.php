<?php

namespace App\Providers;

use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

/**
 * The desktop deliverable (README §2, §14.13), packaged from the same
 * codebase as the web application.
 *
 * Architectural note worth stating plainly: NativePHP would normally ship an
 * embedded SQLite database, but this system's analytics are PostgreSQL-only —
 * the §6 CGPA query uses a named WINDOW clause and the §7 failure-rate query
 * uses COUNT(*) FILTER, neither of which SQLite can parse. The desktop build
 * therefore keeps DB_CONNECTION=pgsql and connects to the institutional
 * PostgreSQL server over the network. `database/nativephp.sqlite` remains for
 * NativePHP's own internal storage only.
 *
 * In other words, the desktop app is a client shell over the same Laravel
 * application, not a self-contained offline copy of the result database.
 */
class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     */
    public function boot(): void
    {
        Window::open()
            ->title(config('app.name').' — Result Processing System')
            ->width(1440)
            ->height(900)
            ->minWidth(1024)
            ->minHeight(700)
            ->rememberState();
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
