<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoyageCars\ConfirmVoyageCarsImportRequest;
use App\Http\Requests\VoyageCars\ImportVoyageCarsRequest;
use App\Http\Requests\VoyageCars\StoreVoyageCarRequest;
use App\Http\Requests\VoyageCars\UpdateVoyageCarRequest;
use App\Jobs\ImportVoyageCarsJob;
use App\Models\Voyage;
use App\Models\VoyageCar;
use App\Models\VoyageCompany;
use App\Services\VoyageCarExcelImportService;
use App\Services\VoyageCarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;

class VoyageCarController extends Controller
{
    public function __construct(
        private readonly VoyageCarService $voyageCarService,
        private readonly VoyageCarExcelImportService $importService
    ) {}

    public function store(StoreVoyageCarRequest $request, Voyage $voyage): RedirectResponse
    {
        Gate::authorize('create', [VoyageCar::class, $voyage]);

        $this->voyageCarService->create($voyage, $request->validated());

        return redirect()
            ->route('voyages.show', $voyage)
            ->with('success', 'Car added to voyage manifest.');
    }

    public function update(
        UpdateVoyageCarRequest $request,
        Voyage $voyage,
        VoyageCar $car
    ): RedirectResponse {
        $this->assertBelongsToVoyage($voyage, $car);
        Gate::authorize('update', $car);

        $this->voyageCarService->update($car, $request->validated());

        return redirect()
            ->route('voyages.show', $voyage)
            ->with('success', 'Car updated successfully.');
    }

    public function destroy(Voyage $voyage, VoyageCar $car): RedirectResponse
    {
        $this->assertBelongsToVoyage($voyage, $car);
        Gate::authorize('delete', $car);

        $this->voyageCarService->delete($car);

        return redirect()
            ->route('voyages.show', $voyage)
            ->with('success', 'Car removed from voyage.');
    }

    public function preview(ImportVoyageCarsRequest $request, Voyage $voyage): RedirectResponse
    {
        Gate::authorize('create', [VoyageCar::class, $voyage]);

        $company = $this->resolveCompany($voyage, (int) $request->validated('voyage_company_id'));
        $this->importService->storeUpload($voyage, $company, $request->file('file'));

        $preview = $this->importService->preview($voyage, $company->fresh());

        session([
            "voyage_import_preview.{$voyage->id}" => [
                'company_id' => $company->id,
                'company_name' => $company->company_name,
                'valid' => $preview['valid'],
                'duplicates' => $preview['duplicates'],
                'skipped' => $preview['skipped'],
                'total_data_rows' => $preview['total_data_rows'],
                'original_name' => $preview['original_name'],
                'rows' => $preview['rows'],
            ],
        ]);

        return redirect()
            ->route('voyages.show', $voyage)
            ->with('success', 'Excel preview ready. Review rows then confirm import.');
    }

    public function confirmImport(ConfirmVoyageCarsImportRequest $request, Voyage $voyage): RedirectResponse
    {
        Gate::authorize('create', [VoyageCar::class, $voyage]);

        $company = $this->resolveCompany($voyage, (int) $request->validated('voyage_company_id'));
        $runAsync = (bool) $request->boolean('run_async');

        $job = new ImportVoyageCarsJob(
            voyageId: $voyage->id,
            companyId: $company->id,
            requestedBy: $request->user()?->id,
            path: $company->excel_file_path
        );

        if ($runAsync) {
            dispatch($job);

            session()->forget("voyage_import_preview.{$voyage->id}");

            return redirect()
                ->route('voyages.show', $voyage)
                ->with('success', 'Import queued. Run `php artisan queue:work --queue=imports` if not already running.');
        }

        Bus::dispatchSync($job);

        $company->refresh();
        session()->forget("voyage_import_preview.{$voyage->id}");

        return redirect()
            ->route('voyages.show', $voyage)
            ->with(
                'success',
                "Import finished. {$company->excel_imported_count} cars added from {$company->excel_original_name}."
            );
    }

    /**
     * Legacy direct import (upload + import immediately).
     */
    public function import(ImportVoyageCarsRequest $request, Voyage $voyage): RedirectResponse
    {
        Gate::authorize('create', [VoyageCar::class, $voyage]);

        $company = $this->resolveCompany($voyage, (int) $request->validated('voyage_company_id'));

        $result = $this->importService->import(
            $voyage,
            $company,
            $request->file('file')
        );

        session()->forget("voyage_import_preview.{$voyage->id}");

        $message = sprintf(
            'Import finished: %d added, %d duplicates, %d skipped.',
            $result['imported'],
            $result['duplicates'],
            $result['skipped']
        );

        if ($result['errors'] !== []) {
            $message .= ' Some rows failed: '.implode(' | ', array_slice($result['errors'], 0, 3));
        }

        return redirect()
            ->route('voyages.show', $voyage)
            ->with('success', $message);
    }

    private function resolveCompany(Voyage $voyage, int $companyId): VoyageCompany
    {
        return VoyageCompany::query()
            ->where('voyage_id', $voyage->id)
            ->whereKey($companyId)
            ->firstOrFail();
    }

    private function assertBelongsToVoyage(Voyage $voyage, VoyageCar $car): void
    {
        abort_unless($car->voyage_id === $voyage->id, 404);
    }
}
