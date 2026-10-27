<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRegistrationWindowRequest;
use App\Models\Course;
use App\Models\RegistrationWindow;
use Illuminate\Http\RedirectResponse;

/**
 * Opens and closes course registration for a session and semester. While a
 * window is closed students can see their registration but not change it.
 */
class RegistrationWindowController extends Controller
{
    public function update(UpdateRegistrationWindowRequest $request): RedirectResponse
    {
        $this->authorize('manage', Course::class);

        $validated = $request->validated();

        RegistrationWindow::query()->updateOrCreate(
            ['session' => $validated['session'], 'semester' => (int) $validated['semester']],
            ['is_open' => (bool) $validated['is_open'], 'updated_by' => $request->user()->getKey()],
        );

        $state = $validated['is_open'] ? 'opened' : 'closed';

        return back()->with('success', "Course registration {$state} for {$validated['session']} semester {$validated['semester']}.");
    }
}
