<?php

namespace App\Policies;

use App\Enums\RequestItemStatus;
use App\Models\RequestItem;
use App\Models\User;

class RequestItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RequestItem $requestItem): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RequestItem $requestItem, ?RequestItemStatus $newStatus = null): bool
    {
        if ($user->plex_token === config('services.plex.seed_token')) {
            return true;
        }

        if ($newStatus === RequestItemStatus::Pending) {
            return $requestItem->actioned_by === $user->id;
        }

        if ($requestItem->actioned_by === null) {
            return true;
        }

        return $requestItem->actioned_by === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RequestItem $requestItem): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RequestItem $requestItem): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RequestItem $requestItem): bool
    {
        return false;
    }
}
