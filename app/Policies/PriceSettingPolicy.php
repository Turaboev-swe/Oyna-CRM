<?php

namespace App\Policies;

use App\Models\PriceSetting;
use App\Models\User;

class PriceSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PriceSetting $priceSetting): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PriceSetting $priceSetting): bool
    {
        return true;
    }

    public function delete(User $user, PriceSetting $priceSetting): bool
    {
        return true;
    }

    public function restore(User $user, PriceSetting $priceSetting): bool
    {
        return true;
    }

    public function forceDelete(User $user, PriceSetting $priceSetting): bool
    {
        return true;
    }
}
