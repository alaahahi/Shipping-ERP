<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MoneyVoucher;
use App\Models\User;

class MoneyVoucherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AccountingView->value);
    }

    public function view(User $user, MoneyVoucher $voucher): bool
    {
        return $user->can(Permission::AccountingView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::AccountingManage->value);
    }

    public function update(User $user, MoneyVoucher $voucher): bool
    {
        return $user->can(Permission::AccountingManage->value) && $voucher->isDraft();
    }

    public function delete(User $user, MoneyVoucher $voucher): bool
    {
        return $user->can(Permission::AccountingManage->value) && $voucher->isDraft();
    }

    public function post(User $user, MoneyVoucher $voucher): bool
    {
        return $user->can(Permission::AccountingManage->value) && $voucher->isDraft();
    }
}
