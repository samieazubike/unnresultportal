<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\StructureController;
use App\Http\Controllers\CourseRegistrationController;
use App\Http\Controllers\Dashboard\DepartmentDashboardController;
use App\Http\Controllers\Dashboard\LecturerDashboardController;
use App\Http\Controllers\Dashboard\StudentDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DesktopShellController;
use App\Http\Controllers\ResultEntryController;
use App\Http\Controllers\ResultPublicationController;
use App\Http\Controllers\ResultReviewController;
use App\Http\Controllers\ResultUploadController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\TranscriptController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
|
| Every route here returns Inertia::render(...) or a file download. There is
| deliberately no /api group and no JSON API for the frontend to consume:
| README §2 makes that a hard architectural rule, and §16 lists "building a
| JSON API and calling it with fetch from React" as breaking the defence.
|
| Authentication is standard Laravel session auth behind the `auth`
| middleware. No Sanctum, no tokens.
|
*/

Route::inertia('/', 'welcome')->name('home');

/*
| Desktop shell only. The NativePHP window renders the web application over
| HTTP, so these are the sole pages the packaged app serves itself — shown
| when that server cannot be reached. Plain Blade rather than Inertia on
| purpose: the offline screen must render without the Vite manifest or any
| network round trip.
*/
Route::get('desktop/offline', [DesktopShellController::class, 'offline'])->name('desktop.offline');
Route::get('desktop/retry', [DesktopShellController::class, 'retry'])->name('desktop.retry');

Route::middleware(['auth', 'verified'])->group(function () {

    // Sends each user to the dashboard for their role.
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    /*
    | Student
    */
    Route::middleware('role:student')->group(function () {
        Route::get('student/dashboard', StudentDashboardController::class)->name('student.dashboard');
        Route::get('student/results', [StudentPortalController::class, 'index'])->name('student.results');

        Route::get('student/registration', [CourseRegistrationController::class, 'index'])->name('student.registration');
        Route::post('student/registration', [CourseRegistrationController::class, 'store'])->name('student.registration.store');
        Route::delete('student/registration/{registration}', [CourseRegistrationController::class, 'destroy'])->name('student.registration.destroy');
    });

    // Own transcript. Staff use the parameterised routes below, which are
    // guarded by StudentPolicy rather than by role.
    Route::get('transcript', TranscriptController::class)->name('transcript');
    Route::get('students/{student}/transcript', TranscriptController::class)->name('students.transcript');
    Route::get('students/{student}/results', [StudentPortalController::class, 'show'])->name('students.results');

    /*
    | Lecturer — score entry
    |
    | Any lecturer on a course's teaching team may enter and correct scores.
    | Only the head may submit the sheet; that is enforced by ResultPolicy,
    | not by the route.
    */
    Route::middleware('role:lecturer')->group(function () {
        Route::get('lecturer/dashboard', LecturerDashboardController::class)->name('lecturer.dashboard');
        Route::get('lecturer/courses', [ResultEntryController::class, 'index'])->name('lecturer.courses');

        Route::get('lecturer/courses/{course}/scores', [ResultEntryController::class, 'show'])->name('scores.show');
        Route::post('lecturer/courses/{course}/scores', [ResultEntryController::class, 'store'])->name('scores.store');
        Route::post('lecturer/courses/{course}/scores/submit', [ResultEntryController::class, 'submit'])->name('scores.submit');

        Route::get('lecturer/courses/{course}/scores/template', [ResultEntryController::class, 'template'])->name('scores.template');
        Route::post('lecturer/courses/{course}/scores/upload', [ResultUploadController::class, 'store'])->name('scores.upload');
    });

    /*
    | Examination officer — review
    |
    | Approve and reject only. Neither action accepts a score field.
    */
    Route::middleware('role:exam_officer')->group(function () {
        Route::get('review', [ResultReviewController::class, 'index'])->name('review.index');
        Route::get('review/{course}/{session}/{semester}', [ResultReviewController::class, 'show'])
            ->where('session', '[0-9]{4}-[0-9]{4}')
            ->name('review.show');
        Route::post('review/approve', [ResultReviewController::class, 'approve'])->name('review.approve');
        Route::post('review/reject', [ResultReviewController::class, 'reject'])->name('review.reject');
    });

    /*
    | Publication — examination officer and ICT director
    |
    | A separate deliberate action from approval (§9). Refuses any result
    | whose integrity signature no longer matches.
    */
    Route::middleware('role:exam_officer,ict_director')->group(function () {
        Route::get('publish', [ResultPublicationController::class, 'index'])->name('publish.index');
        Route::post('publish', [ResultPublicationController::class, 'store'])->name('publish.store');
        Route::get('integrity', [ResultPublicationController::class, 'integrity'])->name('integrity.index');
    });

    /*
    | Departmental and faculty dashboards
    */
    Route::middleware('role:exam_officer,ict_director,admin')->group(function () {
        Route::get('dashboards/department', DepartmentDashboardController::class)->name('department.dashboard');
    });

    /*
    | Administration — users, structure, courses, lecturer assignment.
    | Note there is no score-writing route in this group, by design.
    */
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', AdminDashboardController::class)->name('dashboard');

        Route::get('structure', [StructureController::class, 'index'])->name('structure');
        Route::post('faculties', [StructureController::class, 'storeFaculty'])->name('faculties.store');
        Route::post('departments', [StructureController::class, 'storeDepartment'])->name('departments.store');
        Route::put('settings/threshold', [StructureController::class, 'updateThreshold'])->name('settings.threshold');

        Route::get('users', [StructureController::class, 'users'])->name('users');
        Route::put('users/{user}/role', [StructureController::class, 'updateUserRole'])->name('users.role');
        Route::post('students', [StructureController::class, 'storeStudent'])->name('students.store');

        Route::get('courses', [CourseController::class, 'index'])->name('courses');
        Route::post('courses', [CourseController::class, 'store'])->name('courses.store');
        Route::put('courses/{course}', [CourseController::class, 'update'])->name('courses.update');
        Route::delete('courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');
    });
});

require __DIR__.'/settings.php';
