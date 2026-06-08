<?php

namespace App\Policies;

use App\Models\Bill;
use App\Models\User;

class BillPolicy
{
    /**
     * Staff with the bills permission see any bill; members see bills
     * issued against their own water accounts.
     */
    public function view(User $user, Bill $bill): bool
    {
        if ($user->can('bills.view')) {
            return true;
        }

        return $user->can('bills.view-own')
            && $bill->waterAccount->user_id === $user->id;
    }
}
