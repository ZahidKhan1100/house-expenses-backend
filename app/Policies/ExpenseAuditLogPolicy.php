<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\ExpenseAuditLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExpenseAuditLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(Admin $admin): bool
    {
        return $authUser->can('ViewAny:ExpenseAuditLog');
    }

    public function view(Admin $admin, ExpenseAuditLog $expenseAuditLog): bool
    {
        return $authUser->can('View:ExpenseAuditLog');
    }

    public function create(Admin $admin): bool
    {
        return $authUser->can('Create:ExpenseAuditLog');
    }

    public function update(Admin $admin, ExpenseAuditLog $expenseAuditLog): bool
    {
        return $authUser->can('Update:ExpenseAuditLog');
    }

    public function delete(Admin $admin, ExpenseAuditLog $expenseAuditLog): bool
    {
        return $authUser->can('Delete:ExpenseAuditLog');
    }

    public function deleteAny(Admin $admin): bool
    {
        return $authUser->can('DeleteAny:ExpenseAuditLog');
    }

    public function restore(Admin $admin, ExpenseAuditLog $expenseAuditLog): bool
    {
        return $authUser->can('Restore:ExpenseAuditLog');
    }

    public function forceDelete(Admin $admin, ExpenseAuditLog $expenseAuditLog): bool
    {
        return $authUser->can('ForceDelete:ExpenseAuditLog');
    }

    public function forceDeleteAny(Admin $admin): bool
    {
        return $authUser->can('ForceDeleteAny:ExpenseAuditLog');
    }

    public function restoreAny(Admin $admin): bool
    {
        return $authUser->can('RestoreAny:ExpenseAuditLog');
    }

    public function replicate(Admin $admin, ExpenseAuditLog $expenseAuditLog): bool
    {
        return $authUser->can('Replicate:ExpenseAuditLog');
    }

    public function reorder(Admin $admin): bool
    {
        return $authUser->can('Reorder:ExpenseAuditLog');
    }
}
