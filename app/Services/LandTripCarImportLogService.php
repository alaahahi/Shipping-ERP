<?php

namespace App\Services;

use App\Enums\LandTripCarDeletionSource;
use App\Models\Company;
use App\Models\LandTrip;
use App\Models\LandTripCar;
use App\Models\LandTripCarImport;
use App\Models\User;
use App\Support\ApplicationTimezone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LandTripCarImportLogService
{
    public function __construct(
        private readonly LandTripCarDeletionLogService $deletionLogService
    ) {}

    /**
     * @param  array{imported?: int, updated?: int, skipped?: int, created_ids?: list<int>}  $result
     */
    public function record(
        Company $company,
        LandTrip $trip,
        User $actor,
        ?string $originalFilename,
        array $result
    ): LandTripCarImport {
        $createdIds = array_values(array_unique(array_map(
            'intval',
            $result['created_ids'] ?? []
        )));

        $import = LandTripCarImport::query()->create([
            'company_id' => $company->id,
            'land_trip_id' => $trip->id,
            'user_id' => $actor->id,
            'original_filename' => $this->filename($originalFilename),
            'imported_count' => (int) ($result['imported'] ?? count($createdIds)),
            'updated_count' => (int) ($result['updated'] ?? 0),
            'skipped_count' => (int) ($result['skipped'] ?? 0),
            'created_car_ids' => $createdIds,
        ]);

        Log::info('Land trip Excel import recorded.', [
            'company_id' => $company->id,
            'land_trip_id' => $trip->id,
            'import_id' => $import->id,
            'user_id' => $actor->id,
            'original_filename' => $import->original_filename,
            'imported_count' => $import->imported_count,
            'updated_count' => $import->updated_count,
            'skipped_count' => $import->skipped_count,
        ]);

        return $import;
    }

    public function canUndo(Company $company): bool
    {
        return $this->latestApplied($company) !== null;
    }

    public function undoLatest(Company $company, User $actor): LandTripCarImport
    {
        $import = $this->latestApplied($company);

        if ($import === null) {
            throw ValidationException::withMessages([
                'undo' => 'There is no Excel import to undo.',
            ]);
        }

        return DB::transaction(function () use ($company, $actor, $import): LandTripCarImport {
            $carIds = $import->createdCarIds();
            $deleted = 0;

            if ($carIds !== []) {
                $cars = LandTripCar::query()
                    ->whereIn('id', $carIds)
                    ->whereHas('landTrip', fn ($builder) => $builder->where('company_id', $company->id))
                    ->get();

                foreach ($cars as $car) {
                    $car->delete();
                    $deleted++;
                }

                if ($deleted > 0) {
                    $this->deletionLogService->record(
                        $company,
                        $actor,
                        $cars,
                        LandTripCarDeletionSource::ImportUndo
                    );
                }
            }

            $import->update([
                'undone_at' => now(),
                'undone_by' => $actor->id,
            ]);

            Log::info('Land trip Excel import undone.', [
                'company_id' => $company->id,
                'import_id' => $import->id,
                'user_id' => $actor->id,
                'deleted_created_cars' => $deleted,
                'updated_count' => $import->updated_count,
            ]);

            return $import->fresh(['user', 'undoneByUser']) ?? $import;
        });
    }

    public function paginateForCompany(Company $company, int $perPage = 20): LengthAwarePaginator
    {
        $latestId = $this->latestApplied($company)?->id;

        return LandTripCarImport::query()
            ->where('company_id', $company->id)
            ->with([
                'user:id,name',
                'undoneByUser:id,name',
            ])
            ->latest('id')
            ->paginate($perPage)
            ->through(fn (LandTripCarImport $import) => $this->transform($import, $latestId));
    }

    /**
     * @return array{can_undo: bool}
     */
    public function meta(Company $company): array
    {
        return [
            'can_undo' => $this->canUndo($company),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(LandTripCarImport $import, ?int $latestUndoableId = null): array
    {
        return [
            'id' => $import->id,
            'created_at' => ApplicationTimezone::formatDateTime($import->created_at),
            'user_name' => $import->user?->name,
            'original_filename' => $import->original_filename,
            'imported_count' => $import->imported_count,
            'updated_count' => $import->updated_count,
            'skipped_count' => $import->skipped_count,
            'undone' => $import->isUndone(),
            'undone_at' => ApplicationTimezone::formatDateTime($import->undone_at),
            'undone_by_name' => $import->undoneByUser?->name,
            'can_undo' => $latestUndoableId !== null && (int) $import->id === (int) $latestUndoableId,
        ];
    }

    private function latestApplied(Company $company): ?LandTripCarImport
    {
        return LandTripCarImport::query()
            ->where('company_id', $company->id)
            ->whereNull('undone_at')
            ->latest('id')
            ->first();
    }

    private function filename(?string $originalFilename): string
    {
        $name = trim((string) $originalFilename);

        return $name !== '' ? $name : 'Excel';
    }
}
