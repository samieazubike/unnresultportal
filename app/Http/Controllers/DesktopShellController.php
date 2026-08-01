<?php

namespace App\Http\Controllers;

use App\Services\PortalConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The desktop shell's only local pages.
 *
 * Everything else the desktop app shows is served by the web application over
 * HTTP (see config/desktop.php). These two actions cover the one case the
 * remote app cannot handle for itself: it is not reachable.
 */
class DesktopShellController extends Controller
{
    public function __construct(private readonly PortalConnection $portal) {}

    public function offline(): View
    {
        return view('desktop.offline', [
            'webUrl' => $this->portal->webUrl(),
        ]);
    }

    public function retry(): RedirectResponse
    {
        if ($this->portal->isReachable()) {
            return redirect()->away($this->portal->webUrl());
        }

        return back()->with('error', 'Still no answer from the portal. Check that the server is running and that this machine can reach it.');
    }
}
