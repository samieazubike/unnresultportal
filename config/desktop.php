<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Portal address for the desktop shell
    |--------------------------------------------------------------------------
    |
    | The desktop deliverable is an Electron shell over the running web
    | application rather than a self-contained copy of it: the static PHP
    | binary NativePHP bundles has no pdo_pgsql, so the desktop process
    | cannot open the institutional PostgreSQL database directly.
    |
    | Loading the web app over HTTP means every page the desktop renders is
    | served by the same Laravel application, reading the same PostgreSQL
    | database, through the same Inertia pages — no JSON API, which README §2
    | rules out anyway.
    |
    | 'web_url' is the address of that web application. The health endpoint is
    | probed before the window opens so an unreachable server produces the
    | offline screen instead of a raw Electron error page.
    |
    */

    'web_url' => rtrim(env('DESKTOP_WEB_URL', env('APP_URL', 'http://localhost')), '/'),

    'health_path' => env('DESKTOP_HEALTH_PATH', '/up'),

    // Kept short: this probe runs on the app-launch path and must not stall it.
    'health_timeout' => (int) env('DESKTOP_HEALTH_TIMEOUT', 3),

];
