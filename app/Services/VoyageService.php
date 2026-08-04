<?php

namespace App\Services;

use App\Domain\DefaultPorts;
use App\Enums\Permission;
use App\Enums\VoyageStatus;
use App\Models\Voyage;
use App\Notifications\VoyageStatusChangedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoyageService
{
    public function __construct(
        private readonly NotificationDispatchService $notificationDispatchService,
        private readonly CompanyWhatsappNotificationService $whatsappNotificationService
    ) {}
    /**
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     ship_id?: string|null
     * }  $filters
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Voyage::query()
            ->with('ship:id,name,flag')
            ->latest('sailing_date')
            ->latest('id');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('voyage_number', 'like', "%{$search}%")
                    ->orWhere('pol', 'like', "%{$search}%")
                    ->orWhere('pod', 'like', "%{$search}%")
                    ->orWhere('captain', 'like', "%{$search}%")
                    ->orWhereHas('ship', function ($shipQuery) use ($search): void {
                        $shipQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['ship_id'])) {
            $query->where('ship_id', $filters['ship_id']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array{
     *     ship_id: int,
     *     voyage_number: string,
     *     sailing_date: string,
     *     arrival_date?: string|null,
     *     pol?: string|null,
     *     pod?: string|null,
     *     captain?: string|null,
     *     cost_per_car_aed?: float|int|string,
     *     captain_commission_aed?: float|int|string,
     *     purchase_price_aed?: float|int|string,
     *     notes?: string|null
     * }  $data
     */
    public function create(array $data): Voyage
    {
        return DB::transaction(fn (): Voyage => Voyage::query()->create([
            'ship_id' => $data['ship_id'],
            'voyage_number' => $data['voyage_number'],
            'sailing_date' => $data['sailing_date'],
            'arrival_date' => $data['arrival_date'] ?? null,
            'pol' => $this->nullableOrDefault($data['pol'] ?? null, DefaultPorts::POL),
            'pod' => $this->nullableOrDefault($data['pod'] ?? null, DefaultPorts::POD),
            'captain' => $data['captain'] ?? null,
            'status' => VoyageStatus::Draft->value,
            'cost_per_car_aed' => $data['cost_per_car_aed'] ?? 0,
            'captain_commission_aed' => $data['captain_commission_aed'] ?? 0,
            'purchase_price_aed' => $data['purchase_price_aed'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]));
    }

    /**
     * @param  array{
     *     ship_id: int,
     *     voyage_number: string,
     *     sailing_date: string,
     *     arrival_date?: string|null,
     *     pol?: string|null,
     *     pod?: string|null,
     *     captain?: string|null,
     *     cost_per_car_aed?: float|int|string,
     *     captain_commission_aed?: float|int|string,
     *     purchase_price_aed?: float|int|string,
     *     notes?: string|null
     * }  $data
     */
    public function update(Voyage $voyage, array $data): Voyage
    {
        if (! $voyage->isEditable()) {
            throw ValidationException::withMessages([
                'voyage' => 'Closed voyages cannot be edited.',
            ]);
        }

        return DB::transaction(function () use ($voyage, $data): Voyage {
            $voyage->update([
                'ship_id' => $data['ship_id'],
                'voyage_number' => $data['voyage_number'],
                'sailing_date' => $data['sailing_date'],
                'arrival_date' => $data['arrival_date'] ?? null,
                'pol' => $data['pol'] ?? null,
                'pod' => $data['pod'] ?? null,
                'captain' => $data['captain'] ?? null,
                'cost_per_car_aed' => $data['cost_per_car_aed'] ?? 0,
                'captain_commission_aed' => $data['captain_commission_aed'] ?? 0,
                'purchase_price_aed' => $data['purchase_price_aed'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            return $voyage->fresh('ship:id,name,flag');
        });
    }

    public function transition(Voyage $voyage, VoyageStatus $status): Voyage
    {
        $allowed = $voyage->status->allowedTransitions();

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot change status from {$voyage->status->value} to {$status->value}.",
            ]);
        }

        $from = $voyage->status;
        $voyage->update(['status' => $status->value]);
        $voyage = $voyage->fresh('ship:id,name,flag', 'companies.company');

        $this->notificationDispatchService->notifyByPermissions(
            [Permission::VoyagesView->value, Permission::VoyagesManage->value],
            new VoyageStatusChangedNotification($voyage, $from, $status),
            auth()->id()
        );

        if ($status === VoyageStatus::Active) {
            $this->whatsappNotificationService->notifyVoyageDeparted($voyage);
        } elseif ($status === VoyageStatus::Closed) {
            $this->whatsappNotificationService->notifyVoyageArrived($voyage);
        }

        return $voyage;
    }

    public function delete(Voyage $voyage): void
    {
        if ($voyage->status === VoyageStatus::Closed) {
            throw ValidationException::withMessages([
                'voyage' => 'Closed voyages cannot be deleted.',
            ]);
        }

        $voyage->delete();
    }

    private function nullableOrDefault(?string $value, string $default): string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? $default : $trimmed;
    }
}
