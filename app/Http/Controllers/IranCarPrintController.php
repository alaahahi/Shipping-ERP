<?php

namespace App\Http\Controllers;

use App\Enums\IranCarSaleState;
use App\Models\IranCar;
use App\Services\IranCarService;
use App\Support\ApplicationTimezone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IranCarPrintController extends Controller
{
    public function __construct(
        private readonly IranCarService $iranCarService
    ) {}

    public function list(Request $request): Response
    {
        Gate::authorize('viewAny', IranCar::class);

        $saleState = IranCarSaleState::tryFrom($request->string('sale_state')->toString())
            ?? IranCarSaleState::Unsold;

        $filters = [
            'search' => $request->string('search')->toString(),
            'company_id' => $request->string('company_id')->toString(),
            'border' => $request->string('border')->toString(),
            'sale_state' => $saleState->value,
            'remaining_only' => $request->boolean('remaining_only'),
        ];

        $groups = $this->iranCarService->grouped($filters);
        $summary = [
            'count' => collect($groups)->sum('count'),
            'list_amount' => number_format(collect($groups)->sum(fn ($group) => (float) $group['list_amount']), 2, '.', ''),
            'sale_amount' => number_format(collect($groups)->sum(fn ($group) => (float) $group['sale_amount']), 2, '.', ''),
            'paid_amount' => number_format(collect($groups)->sum(fn ($group) => (float) $group['paid_amount']), 2, '.', ''),
            'remaining_amount' => number_format(collect($groups)->sum(fn ($group) => (float) $group['remaining_amount']), 2, '.', ''),
        ];

        return Inertia::render('IranCars/ListPrint', [
            'groups' => $groups,
            'filters' => $filters,
            'summary' => $summary,
            'printedAt' => ApplicationTimezone::formatNowLabel(),
        ]);
    }

    public function car(IranCar $iran_car): Response
    {
        Gate::authorize('view', $iran_car);

        return Inertia::render('IranCars/PaymentPrint', [
            'car' => $this->iranCarService->transform($iran_car, detailed: true),
            'printedAt' => ApplicationTimezone::formatNowLabel(),
        ]);
    }
}
