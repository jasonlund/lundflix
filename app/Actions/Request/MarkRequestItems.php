<?php

declare(strict_types=1);

namespace App\Actions\Request;

use App\Enums\RequestItemStatus;
use App\Enums\RequestStatus;
use App\Events\RequestFulfilled;
use App\Models\Request;

class MarkRequestItems
{
    /**
     * Mark request items with the given status.
     *
     * @param  array<int, int>  $itemIds
     */
    public function markAs(
        Request $request,
        array $itemIds,
        RequestItemStatus $status,
        ?int $userId = null
    ): int {
        $updates = ['status' => $status];

        // Only track user/time for final statuses
        if (in_array($status, [
            RequestItemStatus::Fulfilled,
            RequestItemStatus::Rejected,
            RequestItemStatus::NotFound,
        ])) {
            $updates['actioned_by'] = $userId ?? auth()->id();
            $updates['actioned_at'] = now();
        } else {
            // Pending status clears tracking
            $updates['actioned_by'] = null;
            $updates['actioned_at'] = null;
        }

        $wasAlreadyFulfilled = $request->status === RequestStatus::Fulfilled; // @phpstan-ignore property.notFound (computed attribute)

        $count = $request->items()
            ->whereIn('id', $itemIds)
            ->update($updates);

        $request->refresh();

        if (! $wasAlreadyFulfilled && $status === RequestItemStatus::Fulfilled && $request->status === RequestStatus::Fulfilled) { // @phpstan-ignore property.notFound (computed attribute)
            RequestFulfilled::dispatch($request);
        }

        return $count;
    }
}
