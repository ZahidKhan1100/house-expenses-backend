<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\Record;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class RecordPolicy
{
    use HandlesAuthorization;

    public function viewAny(User|Admin $user): bool
    {
        return $user instanceof Admin
            ? $user->can('ViewAny:Record')
            : true;
    }

    public function view(User|Admin $user, Record $record): bool
    {
        return $user instanceof Admin
            ? $user->can('View:Record')
            : $user->role === 'admin' || ($record->expense && $user->house_id === $record->expense->house_id);
    }

    public function create(User|Admin $user): bool
    {
        return $user instanceof Admin
            ? $user->can('Create:Record')
            : true;
    }

    public function update(User|Admin $user, Record $record): bool
    {
        return $user instanceof Admin
            ? $user->can('Update:Record')
            : $user->role === 'admin' || $user->id === $record->added_by;
    }

    public function delete(User|Admin $user, Record $record): Response
    {
        if ($user instanceof Admin) {
            return $user->can('Delete:Record')
                ? Response::allow()
                : Response::deny();
        }

        return $user->role === 'admin' || $user->id === $record->added_by
            ? Response::allow()
            : Response::deny('You do not own this record.');
    }

    public function deleteAny(User|Admin $user): bool
    {
        return $user instanceof Admin
            ? $user->can('DeleteAny:Record')
            : true;
    }

    public function restore(User|Admin $user, Record $record): bool
    {
        return $user instanceof Admin
            ? $user->can('Restore:Record')
            : false;
    }

    public function forceDelete(User|Admin $user, Record $record): bool
    {
        return $user instanceof Admin
            ? $user->can('ForceDelete:Record')
            : false;
    }

    public function forceDeleteAny(User|Admin $user): bool
    {
        return $user instanceof Admin
            ? $user->can('ForceDeleteAny:Record')
            : false;
    }

    public function restoreAny(User|Admin $user): bool
    {
        return $user instanceof Admin
            ? $user->can('RestoreAny:Record')
            : false;
    }

    public function replicate(User|Admin $user, Record $record): bool
    {
        return $user instanceof Admin
            ? $user->can('Replicate:Record')
            : false;
    }

    public function reorder(User|Admin $user): bool
    {
        return $user instanceof Admin
            ? $user->can('Reorder:Record')
            : false;
    }
}
