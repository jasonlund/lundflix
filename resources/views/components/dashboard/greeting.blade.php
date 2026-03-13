<?php

use App\Models\RequestItem;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function greeting(): string
    {
        $user = auth()->user();
        $userRequestScope = fn ($query) => $query->where('user_id', $user->id);

        $lastFulfilled = RequestItem::whereHas('request', $userRequestScope)
            ->fulfilled()
            ->selectRaw('DATE(actioned_at) as fulfilled_date, COUNT(*) as item_count')
            ->groupBy('fulfilled_date')
            ->orderByDesc('fulfilled_date')
            ->first();

        $pendingCount = RequestItem::whereHas('request', $userRequestScope)
            ->pending()
            ->count();

        if (! $user->requests()->exists()) {
            return __('lundbergh.dashboard.greeting_new');
        }

        $lines = [];

        if ($lastFulfilled) {
            $daysAgo = (int) Carbon::parse($lastFulfilled->fulfilled_date)->diffInDays(today());
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
