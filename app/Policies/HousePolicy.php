<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\House;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class HousePolicy
{
    use HandlesAuthorization;

    public function viewAny(User|Admin $user): bool
    {
        return $user instanceof Admin
            ? $user->can('ViewAny:House')
            : true;
    }

    public function view(User|Admin $user, House $house): bool
    {
        return $user instanceof Admin
            ? $user->can('View:House')
            : $user->role === 'admin' || $user->house_id === $house->id;
    }

    public function create(User|Admin $user): bool
    {
        return $user instanceof Admin
            ? $user->can('Create:House')
            : true;
    }

    public function update(User|Admin $user, House $house): bool
    {
        return $user instanceof Admin
            ? $user->can('Update:House')
            : $user->role === 'admin';
    }

    public function delete(User|Admin $user, House $house): bool
    {
        return $user instanceof Admin
            ? $user->can('Delete:House')
            : false;
    }

    public function deleteAny(User|Admin $user): bool
    {
        return $user instanceof Admin
            ? $user->can('DeleteAny:House')
            : false;
    }

    public function restore(User|Admin $user, House $house): bool
    {
        return $user instanceof Admin
            ? $user->can('Restore:House')
            : false;
    }

    public function forceDelete(User|Admin $user, House $house): bool
    {
        return $user instanceof Admin
            ? $user->can('ForceDelete:House')
            : false;
    }

    public function forceDeleteAny(User|Admin $user): bool
    {
        return $user instanceof Admin
            ? $user->can('ForceDeleteAny:House')
            : false;
    }

    public function restoreAny(User|Admin $user): bool
    {
        return $user instanceof Admin
            ? $user->can('RestoreAny:House')
            : false;
    }

    public function replicate(User|Admin $user, House $house): bool
    {
        return $user instanceof Admin
            ? $user->can('Replicate:House')
            : false;
    }

    public function reorder(User|Admin $user): bool
    {
        return $user instanceof Admin
            ? $user->can('Reorder:House')
            : false;
    }

    public function join(User|Admin $user, House $house): bool
    {
        if ($user instanceof Admin) {
            return false;
        }

        return $user->role === 'mate';
    }
}
