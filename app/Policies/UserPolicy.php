<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(Admin $admin): bool
    {
        return $admin->can('ViewAny:User');
    }

    public function view(Admin $admin, User $user): bool
    {
        return $admin->can('View:User');
    }

    public function create(Admin $admin): bool
    {
        return $admin->can('Create:User');
    }

    public function update(Admin $admin, User $user): bool
    {
        return $admin->can('Update:User');
    }

    public function delete(Admin $admin, User $user): bool
    {
        return $admin->can('Delete:User');
    }

    public function deleteAny(Admin $admin): bool
    {
        return $admin->can('DeleteAny:User');
    }

    public function restore(Admin $admin, User $user): bool
    {
        return $admin->can('Restore:User');
    }

    public function forceDelete(Admin $admin, User $user): bool
    {
        return $admin->can('ForceDelete:User');
    }

    public function forceDeleteAny(Admin $admin): bool
    {
        return $admin->can('ForceDeleteAny:User');
    }

    public function restoreAny(Admin $admin): bool
    {
        return $admin->can('RestoreAny:User');
    }

    public function replicate(Admin $admin, User $user): bool
    {
        return $admin->can('Replicate:User');
    }

    public function reorder(Admin $admin): bool
    {
        return $admin->can('Reorder:User');
    }
}
