<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\Lead;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeadPolicy
{
    use HandlesAuthorization;

    public function viewAny(Admin $admin): bool
    {
        return $admin->can('ViewAny:Lead');
    }

    public function view(Admin $admin, Lead $lead): bool
    {
        return $admin->can('View:Lead');
    }

    public function create(Admin $admin): bool
    {
        return $admin->can('Create:Lead');
    }

    public function update(Admin $admin, Lead $lead): bool
    {
        return $admin->can('Update:Lead');
    }

    public function delete(Admin $admin, Lead $lead): bool
    {
        return $admin->can('Delete:Lead');
    }

    public function deleteAny(Admin $admin): bool
    {
        return $admin->can('DeleteAny:Lead');
    }

    public function restore(Admin $admin, Lead $lead): bool
    {
        return $admin->can('Restore:Lead');
    }

    public function forceDelete(Admin $admin, Lead $lead): bool
    {
        return $admin->can('ForceDelete:Lead');
    }

    public function forceDeleteAny(Admin $admin): bool
    {
        return $admin->can('ForceDeleteAny:Lead');
    }

    public function restoreAny(Admin $admin): bool
    {
        return $admin->can('RestoreAny:Lead');
    }

    public function replicate(Admin $admin, Lead $lead): bool
    {
        return $admin->can('Replicate:Lead');
    }

    public function reorder(Admin $admin): bool
    {
        return $admin->can('Reorder:Lead');
    }
}
