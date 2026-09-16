<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Worker;

class WorkerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Worker $worker): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Worker $worker): bool
    {
        return true;
    }

    public function delete(User $user, Worker $worker): bool
    {
        return true;
    }

    public function restore(User $user, Worker $worker): bool
    {
        return true;
    }

    public function forceDelete(User $user, Worker $worker): bool
    {
        return true;
    }
}
