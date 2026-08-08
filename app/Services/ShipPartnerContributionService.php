<?php

namespace App\Services;

use App\Enums\Currency;
use App\Models\Owner;
use App\Models\Ship;
use App\Models\ShipPartnerContribution;
use Illuminate\Validation\ValidationException;

class ShipPartnerContributionService
{
    /**
     * @param  array{
     *     owner_id: int,
     *     contribution_date: string,
     *     amount: float|int|string,
     *     currency?: string,
     *     description?: string|null,
     *     reference?: string|null,
     *     created_by?: int|null
     * }  $data
     */
    public function create(Ship $ship, array $data): ShipPartnerContribution
    {
        $this->assertOwnerOnShip($ship, (int) $data['owner_id']);

        return $ship->partnerContributions()->create([
            'owner_id' => $data['owner_id'],
            'contribution_date' => $data['contribution_date'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? Currency::USD->value,
            'description' => $data['description'] ?? null,
            'reference' => $data['reference'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function createMany(Ship $ship, array $rows, ?int $createdBy = null): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (($row['amount'] ?? 0) <= 0 || empty($row['contribution_date'])) {
                continue;
            }
            $this->create($ship, [...$row, 'created_by' => $createdBy]);
            $count++;
        }

        return $count;
    }

    /**
     * @param  array{
     *     owner_id: int,
     *     contribution_date: string,
     *     amount: float|int|string,
     *     currency?: string,
     *     description?: string|null,
     *     reference?: string|null
     * }  $data
     */
    public function update(ShipPartnerContribution $row, array $data): ShipPartnerContribution
    {
        $row->loadMissing(['journalEntry', 'ship']);
        $this->assertNotPosted($row);
        $this->assertOwnerOnShip($row->ship, (int) $data['owner_id']);

        $row->update([
            'owner_id' => $data['owner_id'],
            'contribution_date' => $data['contribution_date'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? Currency::USD->value,
            'description' => $data['description'] ?? null,
            'reference' => $data['reference'] ?? null,
        ]);

        return $row->fresh(['owner', 'journalEntry']);
    }

    public function delete(ShipPartnerContribution $row): void
    {
        $row->loadMissing('journalEntry');
        $this->assertNotPosted($row);
        $row->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(ShipPartnerContribution $row): array
    {
        $row->loadMissing(['owner:id,name', 'journalEntry']);
        $posted = $row->isPostedToAccounting();

        return [
            'id' => $row->id,
            'ship_id' => $row->ship_id,
            'owner_id' => $row->owner_id,
            'owner_name' => $row->owner?->name,
            'contribution_date' => $row->contribution_date?->format('Y-m-d'),
            'amount' => number_format((float) $row->amount, 2, '.', ''),
            'currency' => $row->currency->value,
            'description' => $row->description,
            'reference' => $row->reference,
            'is_posted' => $posted,
            'can_post' => in_array($row->currency->value, [Currency::USD->value, Currency::AED->value], true) && ! $posted,
            'journal_entry_id' => $row->journalEntry?->id,
            'journal_voucher' => $row->journalEntry?->voucher_number,
        ];
    }

    private function assertOwnerOnShip(Ship $ship, int $ownerId): void
    {
        $exists = $ship->ownerships()->where('owner_id', $ownerId)->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'owner_id' => 'Selected partner is not an owner of this ship.',
            ]);
        }

        if (! Owner::query()->whereKey($ownerId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'owner_id' => 'Selected partner is inactive.',
            ]);
        }
    }

    private function assertNotPosted(ShipPartnerContribution $row): void
    {
        if ($row->isPostedToAccounting()) {
            throw ValidationException::withMessages([
                'contribution' => 'Posted partner payments cannot be edited or deleted. Void the journal first.',
            ]);
        }
    }
}
