<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\VisualizationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __construct(private readonly VisualizationService $visualization) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('admin/dashboard', [
            'summary' => $this->visualization->institutionSummary(),
            'departmentPerformance' => $this->visualization->departmentPerformance(),
            'facultyPerformance' => $this->visualization->facultyPerformance(),
            'failureRates' => array_slice($this->visualization->courseFailureRates(), 0, 12),
            'failureThreshold' => Setting::failureThreshold(),
            'roleCounts' => User::query()
                ->selectRaw('role, COUNT(*) as total')
                ->groupBy('role')
                ->pluck('total', 'role')
                ->mapWithKeys(fn (int $total, string $role) => [
                    $role => ['label' => UserRole::from($role)->label(), 'total' => $total],
                ]),
        ]);
    }
}
