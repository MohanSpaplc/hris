<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

class JobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('jobs.view');
    }

    public function view(User $user, Job $job): bool
    {
        return $user->hasPermission('jobs.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('jobs.create');
    }

    public function update(User $user, Job $job): bool
    {
        return $user->hasPermission('jobs.update');
    }

    public function delete(User $user, Job $job): bool
    {
        return $user->hasPermission('jobs.delete');
    }
}
