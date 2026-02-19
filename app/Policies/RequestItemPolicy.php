<?php

namespace App\Policies;

use App\Enums\RequestItemStatus;
use App\Models\RequestItem;
use App\Models\User;

class RequestItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RequestItem $requestItem): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, RequestItem $requestItem, ?RequestItemStatus $newStatus = null): bool
    {
        if ($user->isAdmin()) {
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

    public function delete(User $user, RequestItem $requestItem): bool
    {
        return false;
    }

    public function restore(User $user, RequestItem $requestItem): bool
    {
        return false;
    }

    public function forceDelete(User $user, RequestItem $requestItem): bool
    {
        return false;
    }
}
