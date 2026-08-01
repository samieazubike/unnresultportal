<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Where the desktop shell finds the web application, and whether it is up.
 *
 * The desktop deliverable renders the deployed web app rather than querying
 * the database itself (see config/desktop.php for why), so "is the portal
 * reachable" is the one thing it has to answer before opening a window.
 */
class PortalConnection
{
    /**
     * The address of the web application the desktop shell renders.
     */
    public function webUrl(): string
    {
        return (string) config('desktop.web_url');
    }

    /**
     * Probe the web application's health endpoint.
     *
     * Never throws: this runs on the app-launch path, and an unreachable
     * server is an expected state that must produce the offline screen.
     */
    public function isReachable(): bool
    {
        try {
            return Http::timeout((int) config('desktop.health_timeout'))
                ->get($this->webUrl().config('desktop.health_path'))
                ->successful();
        } catch (Throwable) {
            return false;
        }
    }
}
