<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sends each user to the dashboard for their role (README §1: "role-specific
 * dashboards" are part of the contribution being claimed).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->route(match ($request->user()->role) {
            UserRole::Student => 'student.dashboard',
            UserRole::Lecturer => 'lecturer.dashboard',
            UserRole::ExamOfficer, UserRole::IctDirector => 'department.dashboard',
            UserRole::Admin => 'admin.dashboard',
        });
    }
}
