<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\Category;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(Admin $admin): bool
    {
        return $authUser->can('ViewAny:Category');
    }

    public function view(Admin $admin, Category $category): bool
    {
        return $authUser->can('View:Category');
    }

    public function create(Admin $admin): bool
    {
        return $authUser->can('Create:Category');
    }

    public function update(Admin $admin, Category $category): bool
    {
        return $authUser->can('Update:Category');
    }

    public function delete(Admin $admin, Category $category): bool
    {
        return $authUser->can('Delete:Category');
    }

    public function deleteAny(Admin $admin): bool
    {
        return $authUser->can('DeleteAny:Category');
    }

    public function restore(Admin $admin, Category $category): bool
    {
        return $authUser->can('Restore:Category');
    }

    public function forceDelete(Admin $admin, Category $category): bool
    {
        return $authUser->can('ForceDelete:Category');
    }

    public function forceDeleteAny(Admin $admin): bool
    {
        return $authUser->can('ForceDeleteAny:Category');
    }

    public function restoreAny(Admin $admin): bool
    {
        return $authUser->can('RestoreAny:Category');
    }

    public function replicate(Admin $admin, Category $category): bool
    {
        return $authUser->can('Replicate:Category');
    }

    public function reorder(Admin $admin): bool
    {
        return $authUser->can('Reorder:Category');
    }
}
