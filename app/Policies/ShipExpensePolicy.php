<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Ship;
use App\Models\ShipExpense;
use App\Models\User;

class ShipExpensePolicy
{
    public function create(User $user, ?Ship $ship = null): bool
    {
        return $user->can(Permission::ShipsManage->value);
    }

    public function update(User $user, ShipExpense $expense): bool
    {
        $expense->loadMissing('journalEntry');

        return $user->can(Permission::ShipsManage->value)
            && ! $expense->isPostedToAccounting();
    }

    public function updateAttachment(User $user, ShipExpense $expense): bool
    {
        return $user->can(Permission::ShipsManage->value);
    }

    public function delete(User $user, ShipExpense $expense): bool
    {
        $expense->loadMissing('journalEntry');

        return $user->can(Permission::ShipsManage->value)
            && ! $expense->isPostedToAccounting();
    }

    public function post(User $user, ShipExpense $expense): bool
    {
        $expense->loadMissing('journalEntry');

        return $user->can(Permission::ShipsManage->value)
            && $user->can(Permission::AccountingManage->value)
            && in_array($expense->currency?->value, ['USD', 'AED'], true)
            && ! $expense->isPostedToAccounting();
    }
}
