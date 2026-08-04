<?php

namespace App\Services;

use App\Models\Ship;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipService
{
    /**
     * @param  array{search?: string|null, active?: string|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Ship::query()
            ->withCount(['voyages', 'ownerships'])
            ->orderBy('name');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('flag', 'like', "%{$search}%")
                    ->orWhere('imo_number', 'like', "%{$search}%")
                    ->orWhere('call_sign', 'like', "%{$search}%");
            });
        }

        if (($filters['active'] ?? '') !== '') {
            $query->where('is_active', $filters['active'] === '1');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array{
     *     name: string,
     *     flag?: string|null,
     *     imo_number?: string|null,
     *     call_sign?: string|null,
     *     default_captain?: string|null,
     *     is_active?: bool,
     *     notes?: string|null
     * }  $data
     */
    public function create(array $data): Ship
    {
        return DB::transaction(fn (): Ship => Ship::query()->create([
            'name' => $data['name'],
            'flag' => $data['flag'] ?? null,
            'imo_number' => $data['imo_number'] ?? null,
            'call_sign' => $data['call_sign'] ?? null,
            'default_captain' => $data['default_captain'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'notes' => $data['notes'] ?? null,
        ]));
    }

    /**
     * @param  array{
     *     name: string,
     *     flag?: string|null,
     *     imo_number?: string|null,
     *     call_sign?: string|null,
     *     default_captain?: string|null,
     *     is_active?: bool,
     *     notes?: string|null
     * }  $data
     */
    public function update(Ship $ship, array $data): Ship
    {
        return DB::transaction(function () use ($ship, $data): Ship {
            $ship->update([
                'name' => $data['name'],
                'flag' => $data['flag'] ?? null,
                'imo_number' => $data['imo_number'] ?? null,
                'call_sign' => $data['call_sign'] ?? null,
                'default_captain' => $data['default_captain'] ?? null,
                'is_active' => $data['is_active'] ?? $ship->is_active,
                'notes' => $data['notes'] ?? null,
            ]);

            return $ship->fresh();
        });
    }

    public function delete(Ship $ship): void
    {
        if ($ship->voyages()->exists()) {
            throw ValidationException::withMessages([
                'ship' => 'Ships with voyage history cannot be deleted.',
            ]);
        }

        $ship->delete();
    }

    /**
     * @return list<array{id: int, label: string, default_captain: string|null}>
     */
    public function options(bool $activeOnly = true): array
    {
        return Ship::query()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name', 'flag', 'default_captain'])
            ->map(fn (Ship $ship) => [
                'id' => $ship->id,
                'label' => $ship->flag ? "{$ship->name} ({$ship->flag})" : $ship->name,
                'default_captain' => $ship->default_captain,
            ])
            ->all();
    }
}
