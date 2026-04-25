<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\Settlement;
use Illuminate\Auth\Access\HandlesAuthorization;

class SettlementPolicy
{
    use HandlesAuthorization;

    public function viewAny(Admin $admin): bool
    {
        return $authUser->can('ViewAny:Settlement');
    }

    public function view(Admin $admin, Settlement $settlement): bool
    {
        return $authUser->can('View:Settlement');
    }

    public function create(Admin $admin): bool
    {
        return $authUser->can('Create:Settlement');
    }

    public function update(Admin $admin, Settlement $settlement): bool
    {
        return $authUser->can('Update:Settlement');
    }

    public function delete(Admin $admin, Settlement $settlement): bool
    {
        return $authUser->can('Delete:Settlement');
    }

    public function deleteAny(Admin $admin): bool
    {
        return $authUser->can('DeleteAny:Settlement');
    }

    public function restore(Admin $admin, Settlement $settlement): bool
    {
        return $authUser->can('Restore:Settlement');
    }

    public function forceDelete(Admin $admin, Settlement $settlement): bool
    {
        return $authUser->can('ForceDelete:Settlement');
    }

    public function forceDeleteAny(Admin $admin): bool
    {
        return $authUser->can('ForceDeleteAny:Settlement');
    }

    public function restoreAny(Admin $admin): bool
    {
        return $authUser->can('RestoreAny:Settlement');
    }

    public function replicate(Admin $admin, Settlement $settlement): bool
    {
        return $authUser->can('Replicate:Settlement');
    }

    public function reorder(Admin $admin): bool
    {
        return $authUser->can('Reorder:Settlement');
    }
}
