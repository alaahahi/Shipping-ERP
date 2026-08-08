<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Ship;
use App\Models\ShipPartnerContribution;
use App\Models\User;

class ShipPartnerContributionPolicy
{
    public function create(User $user, ?Ship $ship = null): bool
    {
        return $user->can(Permission::ShipsManage->value);
    }

    public function update(User $user, ShipPartnerContribution $contribution): bool
    {
        $contribution->loadMissing('journalEntry');

        return $user->can(Permission::ShipsManage->value)
            && ! $contribution->isPostedToAccounting();
    }

    public function delete(User $user, ShipPartnerContribution $contribution): bool
    {
        $contribution->loadMissing('journalEntry');

        return $user->can(Permission::ShipsManage->value)
            && ! $contribution->isPostedToAccounting();
    }

    public function post(User $user, ShipPartnerContribution $contribution): bool
    {
        $contribution->loadMissing('journalEntry');

        return $user->can(Permission::ShipsManage->value)
            && $user->can(Permission::AccountingManage->value)
            && in_array($contribution->currency?->value, ['USD', 'AED'], true)
            && ! $contribution->isPostedToAccounting();
    }
}
