<?php

namespace App\Http\Controllers;

use App\Enums\IranBorder;
use App\Enums\IranCarSaleState;
use App\Enums\Permission;
use App\Http\Requests\IranCars\ConfirmIranCarsImportRequest;
use App\Http\Requests\IranCars\ImportIranCarsRequest;
use App\Models\IranCar;
use App\Services\CompanyService;
use App\Services\IranCarExcelImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IranCarImportController extends Controller
{
    public function __construct(
        private readonly IranCarExcelImportService $importService,
        private readonly CompanyService $companyService
    ) {}

    public function create(Request $request): Response
    {
        Gate::authorize('create', IranCar::class);

        $saleState = IranCarSaleState::tryFrom($request->string('sale_state')->toString())
            ?? IranCarSaleState::tryFrom((string) session(IranCarExcelImportService::SESSION_SALE_STATE))
            ?? IranCarSaleState::Unsold;

        $preview = null;
        if (session(IranCarExcelImportService::SESSION_PATH)) {
            try {
                $preview = $this->importService->preview(
                    defaultCompanyId: $request->integer('company_id') ?: null,
                    defaultBorder: $request->string('border')->toString() ?: null,
                );
            } catch (\Throwable) {
                $preview = null;
            }
        }

        return Inertia::render('IranCars/Import', [
            'companies' => $this->companyService->options(),
            'borders' => collect(IranBorder::cases())->map(fn (IranBorder $border) => [
                'value' => $border->value,
                'label' => $border->label(),
            ])->all(),
            'preview' => $preview,
            'defaults' => [
                'company_id' => $request->integer('company_id') ?: null,
                'border' => $request->string('border')->toString(),
                'sale_state' => $saleState->value,
            ],
            'canManage' => $request->user()?->can(Permission::IranCarsManage->value) ?? false,
        ]);
    }

    public function preview(ImportIranCarsRequest $request): RedirectResponse
    {
        Gate::authorize('create', IranCar::class);
        $validated = $request->validated();
        $this->importService->storeUpload($request->file('file'), $request->user(), $validated['sale_state']);

        return redirect()
            ->route('iran-cars.import', [
                'company_id' => $validated['company_id'] ?? null,
                'border' => $validated['border'] ?? null,
                'sale_state' => $validated['sale_state'],
            ])
            ->with('success', 'Excel uploaded. Review the preview then confirm import.');
    }

    public function confirm(ConfirmIranCarsImportRequest $request): RedirectResponse
    {
        Gate::authorize('create', IranCar::class);
        $validated = $request->validated();
        $result = $this->importService->confirm(
            $request->user(),
            (int) $validated['company_id'],
            $validated['border'] ?? null,
            $validated['sale_state']
        );

        return redirect()
            ->route('iran-cars.index', ['sale_state' => $validated['sale_state']])
            ->with(
                'success',
                "Iran cars import: {$result['imported']} imported, {$result['duplicates']} duplicates, {$result['skipped']} skipped."
            );
    }
}
