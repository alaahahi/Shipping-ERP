<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Voyage;
use App\Models\VoyageExpense;

class VoyageExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::VoyagesView->value);
    }

    public function view(User $user, VoyageExpense $expense): bool
    {
        return $user->can(Permission::VoyagesView->value);
    }

    public function create(User $user, ?Voyage $voyage = null): bool
    {
        return $user->can(Permission::VoyagesManage->value)
            && ($voyage === null || $voyage->isEditable());
    }

    public function update(User $user, VoyageExpense $expense): bool
    {
        $expense->loadMissing('journalEntry');

        return $user->can(Permission::VoyagesManage->value)
            && $expense->voyage?->isEditable()
            && ! $expense->isPostedToAccounting();
    }

    public function delete(User $user, VoyageExpense $expense): bool
    {
        $expense->loadMissing('journalEntry');

        return $user->can(Permission::VoyagesManage->value)
            && $expense->voyage?->isEditable()
            && ! $expense->isPostedToAccounting();
    }

    public function post(User $user, VoyageExpense $expense): bool
    {
        $expense->loadMissing('journalEntry');

        return $user->can(Permission::VoyagesManage->value)
            && $user->can(Permission::AccountingManage->value)
            && in_array($expense->currency?->value, ['USD', 'AED'], true)
            && ! $expense->isPostedToAccounting();
    }
}
