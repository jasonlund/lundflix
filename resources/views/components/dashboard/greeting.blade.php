<?php

use App\Models\RequestItem;
use App\Support\UserTime;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function greeting(): string
    {
        $user = auth()->user();
        $userRequestScope = fn ($query) => $query->where('user_id', $user->id);

        $tz = UserTime::timezone();

        $latestFulfilled = RequestItem::whereHas('request', $userRequestScope)
            ->fulfilled()
            ->latest('actioned_at')
            ->first();

        $lastFulfilled = null;

        if ($latestFulfilled) {
            $fulfilledDate = UserTime::toUserTz($latestFulfilled->actioned_at)->startOfDay();

            $utcStart = $fulfilledDate->copy()->setTimezone('UTC');
            $utcEnd = $fulfilledDate
                ->copy()
                ->endOfDay()
                ->setTimezone('UTC');

            $itemCount = RequestItem::whereHas('request', $userRequestScope)
                ->fulfilled()
                ->whereBetween('actioned_at', [$utcStart, $utcEnd])
                ->count();

            $lastFulfilled = (object) [
                'fulfilled_date' => $fulfilledDate,
                'item_count' => $itemCount,
            ];
        }

        $pendingCount = RequestItem::whereHas('request', $userRequestScope)
            ->pending()
            ->count();

        if (! $user->requests()->exists()) {
            return __('lundbergh.dashboard.greeting_new');
        }

        $lines = [];

        if ($lastFulfilled) {
            $daysAgo = (int) $lastFulfilled->fulfilled_date->diffInDays(today($tz));
            $when = match (true) {
                $daysAgo === 0 => __('lundbergh.dashboard.when_today'),
                $daysAgo === 1 => __('lundbergh.dashboard.when_yesterday'),
                default => trans_choice('lundbergh.dashboard.when_days_ago', $daysAgo, ['count' => $daysAgo]),
            };
            $lines[] = trans_choice('lundbergh.dashboard.last_fulfilled', $lastFulfilled->item_count, [
                'count' => $lastFulfilled->item_count,
                'when' => $when,
            ]);
        }

        if ($pendingCount > 0) {
            $lines[] = trans_choice('lundbergh.dashboard.pending', $pendingCount, [
                'count' => $pendingCount,
            ]);
        }

        $lines[] = __('lundbergh.dashboard.review_requests');

        return implode('<br>', $lines);
    }
};

?>

<div>
    <x-lundbergh-bubble contentTag="div">
        {!! $this->greeting !!}
    </x-lundbergh-bubble>
</div>
