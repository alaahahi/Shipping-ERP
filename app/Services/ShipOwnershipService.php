<?php

namespace App\Services;

use App\Models\Owner;
use App\Models\Ship;
use App\Models\ShipOwnership;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipOwnershipService
{
    /**
     * @param  array{
     *     owner_id?: int|null,
     *     owner_name?: string|null,
     *     owner_phone?: string|null,
     *     owner_email?: string|null,
     *     share_percent: float|int|string,
     *     is_managing?: bool,
     *     effective_from?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function attach(Ship $ship, array $data): ShipOwnership
    {
        return DB::transaction(function () use ($ship, $data): ShipOwnership {
            $owner = $this->resolveOwner($data);
            $share = round((float) $data['share_percent'], 2);

            if ($share <= 0 || $share > 100) {
                throw ValidationException::withMessages([
                    'share_percent' => 'Ownership share must be between 0.01 and 100.',
                ]);
            }

            $existing = $ship->ownerships()
                ->where('owner_id', $owner->id)
                ->exists();

            if ($existing) {
                throw ValidationException::withMessages([
                    'owner_id' => 'This owner is already linked to the ship.',
                ]);
            }

            $this->assertShareCapacity($ship, $share);

            if (! empty($data['is_managing'])) {
                $ship->ownerships()->update(['is_managing' => false]);
            }

            return $ship->ownerships()->create([
                'owner_id' => $owner->id,
                'share_percent' => $share,
                'is_managing' => (bool) ($data['is_managing'] ?? false),
                'effective_from' => $data['effective_from'] ?? null,
                'notes' => $data['notes'] ?? null,
            ])->load('owner');
        });
    }

    /**
     * @param  array{
     *     share_percent: float|int|string,
     *     is_managing?: bool,
     *     effective_from?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function update(ShipOwnership $ownership, array $data): ShipOwnership
    {
        return DB::transaction(function () use ($ownership, $data): ShipOwnership {
            $ownership->loadMissing('ship');
            $share = round((float) $data['share_percent'], 2);

            if ($share <= 0 || $share > 100) {
                throw ValidationException::withMessages([
                    'share_percent' => 'Ownership share must be between 0.01 and 100.',
                ]);
            }

            $this->assertShareCapacity($ownership->ship, $share, $ownership->id);

            if (! empty($data['is_managing'])) {
                $ownership->ship->ownerships()
                    ->where('id', '!=', $ownership->id)
                    ->update(['is_managing' => false]);
            }

            $ownership->update([
                'share_percent' => $share,
                'is_managing' => (bool) ($data['is_managing'] ?? false),
                'effective_from' => $data['effective_from'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            return $ownership->fresh('owner');
        });
    }

    public function detach(ShipOwnership $ownership): void
    {
        $ownership->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(ShipOwnership $ownership): array
    {
        $ownership->loadMissing('owner');

        return [
            'id' => $ownership->id,
            'ship_id' => $ownership->ship_id,
            'owner_id' => $ownership->owner_id,
            'owner_name' => $ownership->owner?->name,
            'owner_phone' => $ownership->owner?->phone,
            'owner_email' => $ownership->owner?->email,
            'share_percent' => number_format((float) $ownership->share_percent, 2, '.', ''),
            'is_managing' => $ownership->is_managing,
            'effective_from' => $ownership->effective_from?->format('Y-m-d'),
            'notes' => $ownership->notes,
        ];
    }

    /**
     * @return array{total_share: string, remaining: string, is_complete: bool, owners_count: int}
     */
    public function summary(Ship $ship): array
    {
        $total = (float) $ship->ownerships()->sum('share_percent');

        return [
            'total_share' => number_format($total, 2, '.', ''),
            'remaining' => number_format(max(0, 100 - $total), 2, '.', ''),
            'is_complete' => abs($total - 100) < 0.01,
            'owners_count' => $ship->ownerships()->count(),
        ];
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function ownerOptions(): array
    {
        return Owner::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone'])
            ->map(fn (Owner $owner) => [
                'id' => $owner->id,
                'label' => $owner->phone ? "{$owner->name} ({$owner->phone})" : $owner->name,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveOwner(array $data): Owner
    {
        if (! empty($data['owner_id'])) {
            $owner = Owner::query()->whereKey($data['owner_id'])->where('is_active', true)->first();
            if (! $owner) {
                throw ValidationException::withMessages([
                    'owner_id' => 'Owner not found.',
                ]);
            }

            return $owner;
        }

        $name = trim((string) ($data['owner_name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages([
                'owner_name' => 'Select an existing owner or enter a new owner name.',
            ]);
        }

        return Owner::query()->create([
            'name' => $name,
            'phone' => $data['owner_phone'] ?? null,
            'email' => $data['owner_email'] ?? null,
            'is_active' => true,
        ]);
    }

    private function assertShareCapacity(Ship $ship, float $share, ?int $ignoreId = null): void
    {
        $query = $ship->ownerships();
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        $used = (float) $query->sum('share_percent');
        if (round($used + $share, 2) > 100) {
            throw ValidationException::withMessages([
                'share_percent' => 'Total ownership cannot exceed 100%. Remaining: '
                    .number_format(max(0, 100 - $used), 2).'%.',
            ]);
        }
    }
}
