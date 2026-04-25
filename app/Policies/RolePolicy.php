<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    use HandlesAuthorization;

    public function viewAny(Admin $admin): bool
    {
        return $authUser->can('ViewAny:Role');
    }

    public function view(Admin $admin, Role $role): bool
    {
        return $authUser->can('View:Role');
    }

    public function create(Admin $admin): bool
    {
        return $authUser->can('Create:Role');
    }

    public function update(Admin $admin, Role $role): bool
    {
        return $authUser->can('Update:Role');
    }

    public function delete(Admin $admin, Role $role): bool
    {
        return $authUser->can('Delete:Role');
    }

    public function deleteAny(Admin $admin): bool
    {
        return $authUser->can('DeleteAny:Role');
    }

    public function restore(Admin $admin, Role $role): bool
    {
        return $authUser->can('Restore:Role');
    }

    public function forceDelete(Admin $admin, Role $role): bool
    {
        return $authUser->can('ForceDelete:Role');
    }

    public function forceDeleteAny(Admin $admin): bool
    {
        return $authUser->can('ForceDeleteAny:Role');
    }

    public function restoreAny(Admin $admin): bool
    {
        return $authUser->can('RestoreAny:Role');
    }

    public function replicate(Admin $admin, Role $role): bool
    {
        return $authUser->can('Replicate:Role');
    }

    public function reorder(Admin $admin): bool
    {
        return $authUser->can('Reorder:Role');
    }
}
