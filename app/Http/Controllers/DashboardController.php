<?php

namespace App\Http\Controllers;

use App\Enums\JournalStatus;
use App\Enums\VoyageStatus;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Ship;
use App\Models\Voyage;
use App\Services\VoyageReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly VoyageReportService $voyageReportService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canReports = $user?->can('reports.view') ?? false;
        $canVoyages = $user?->can('voyages.view') ?? false;

        return Inertia::render('Dashboard', [
            'stats' => [
                'ships' => $user?->can('ships.view')
                    ? Ship::query()->where('is_active', true)->count()
                    : null,
                'voyages_active' => $canVoyages
                    ? Voyage::query()->where('status', VoyageStatus::Active)->count()
                    : null,
                'voyages_draft' => $canVoyages
                    ? Voyage::query()->where('status', VoyageStatus::Draft)->count()
                    : null,
                'accounts' => $user?->can('accounting.view')
                    ? Account::query()->where('is_active', true)->count()
                    : null,
                'journals_draft' => $user?->can('accounting.view')
                    ? JournalEntry::query()->where('status', JournalStatus::Draft)->count()
                    : null,
            ],
            'monthOverview' => ($canReports || $canVoyages)
                ? $this->voyageReportService->overview()
                : null,
            'recentVoyages' => $canVoyages
                ? Voyage::query()
                    ->with('ship:id,name')
                    ->latest('sailing_date')
                    ->latest('id')
                    ->limit(5)
                    ->get()
                    ->map(fn (Voyage $voyage) => [
                        'id' => $voyage->id,
                        'voyage_number' => $voyage->voyage_number,
                        'sailing_date' => $voyage->sailing_date?->format('Y-m-d'),
                        'ship_name' => $voyage->ship?->name,
                        'status' => $voyage->status->value,
                        'status_label' => $voyage->status->label(),
                        'status_tone' => $voyage->status->tone(),
                        'route' => trim(($voyage->pol ?? '—').' → '.($voyage->pod ?? '—')),
                    ])
                : [],
        ]);
    }
}
