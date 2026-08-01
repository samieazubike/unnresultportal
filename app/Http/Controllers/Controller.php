<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * Policy checks run throughout the result workflow, so every controller
     * gets $this->authorize().
     */
    use AuthorizesRequests;
}
