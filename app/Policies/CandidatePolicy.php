<?php

namespace App\Policies;

use App\Models\Candidate;
use App\Models\User;

class CandidatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('candidates.view');
    }

    public function view(User $user, Candidate $candidate): bool
    {
        return $user->hasPermission('candidates.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('candidates.create');
    }

    public function update(User $user, Candidate $candidate): bool
    {
        return $user->hasPermission('candidates.update');
    }

    public function delete(User $user, Candidate $candidate): bool
    {
        return $user->hasPermission('candidates.delete');
    }
}
