<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared policy for countries, practice areas, clients, client managers and vendors.
 */
class MasterDataPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('masters.view');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->hasPermission('masters.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('masters.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasPermission('masters.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasPermission('masters.delete');
    }
}
