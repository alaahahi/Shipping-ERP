<?php

namespace App\Http\Controllers;

use App\Enums\IranBorder;
use App\Enums\IranCarSaleState;
use App\Enums\Permission;
use App\Http\Requests\IranCars\SellIranCarRequest;
use App\Http\Requests\IranCars\StoreIranCarRequest;
use App\Http\Requests\IranCars\UpdateIranCarRequest;
use App\Models\IranCar;
use App\Services\CompanyService;
use App\Services\IranCarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IranCarController extends Controller
{
    public function __construct(
        private readonly IranCarService $iranCarService,
        private readonly CompanyService $companyService
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', IranCar::class);
        $filters = $this->filters($request);

        return Inertia::render('IranCars/Index', [
            'groups' => $this->iranCarService->grouped($filters),
            'counts' => $this->iranCarService->saleStateCounts(),
            'filters' => $filters,
            'companies' => $this->companyService->options(),
            'borders' => $this->borderOptions(),
            'canManage' => $request->user()?->can(Permission::IranCarsManage->value) ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', IranCar::class);

        return Inertia::render('IranCars/Create', [
            'companies' => $this->companyService->options(),
            'borders' => $this->borderOptions(),
        ]);
    }

    public function store(StoreIranCarRequest $request): RedirectResponse
    {
        $car = $this->iranCarService->create($request->validated(), $request->user());

        return redirect()->route('iran-cars.show', $car)
            ->with('success', "Iran car {$car->vin} created.");
    }

    public function show(Request $request, IranCar $iran_car): Response
    {
        Gate::authorize('view', $iran_car);

        return Inertia::render('IranCars/Show', [
            'car' => $this->iranCarService->transform($iran_car, detailed: true),
            'cashAccounts' => $this->iranCarService->cashBankAccountOptions(),
            'canManage' => $request->user()?->can(Permission::IranCarsManage->value) ?? false,
        ]);
    }

    public function edit(IranCar $iran_car): Response
    {
        Gate::authorize('update', $iran_car);

        return Inertia::render('IranCars/Edit', [
            'car' => $this->iranCarService->transform($iran_car, detailed: true),
            'companies' => $this->companyService->options(),
            'borders' => $this->borderOptions(),
        ]);
    }

    public function update(UpdateIranCarRequest $request, IranCar $iran_car): RedirectResponse
    {
        Gate::authorize('update', $iran_car);
        $this->iranCarService->update($iran_car, $request->validated(), $request->user());

        return redirect()->route('iran-cars.show', $iran_car)->with('success', 'Iran car updated.');
    }

    public function sell(SellIranCarRequest $request, IranCar $iran_car): RedirectResponse
    {
        Gate::authorize('update', $iran_car);
        $this->iranCarService->markSold($iran_car, $request->validated(), $request->user());

        return redirect()
            ->route('iran-cars.show', $iran_car)
            ->with('success', "Iran car {$iran_car->vin} moved to sold.");
    }

    public function destroy(Request $request, IranCar $iran_car): RedirectResponse
    {
        Gate::authorize('delete', $iran_car);
        $vin = $iran_car->vin;
        $this->iranCarService->delete($iran_car, $request->user());

        return redirect()
            ->route('iran-cars.index', ['sale_state' => IranCarSaleState::Unsold->value])
            ->with('success', "Iran car {$vin} deleted.");
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', IranCar::class);

        return $this->iranCarService->exportExcel($this->filters($request));
    }

    /**
     * @return array{search: string, company_id: string, border: string, sale_state: string, remaining_only: bool}
     */
    private function filters(Request $request): array
    {
        $saleState = IranCarSaleState::tryFrom($request->string('sale_state')->toString())
            ?? IranCarSaleState::Unsold;

        return [
            'search' => $request->string('search')->toString(),
            'company_id' => $request->string('company_id')->toString(),
            'border' => $request->string('border')->toString(),
            'sale_state' => $saleState->value,
            'remaining_only' => $request->boolean('remaining_only'),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function borderOptions(): array
    {
        return collect(IranBorder::cases())
            ->map(fn (IranBorder $border) => [
                'value' => $border->value,
                'label' => $border->label(),
            ])
            ->all();
    }
}
