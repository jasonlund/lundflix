<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\RequestFulfilled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendRequestFulfilledNotification implements ShouldQueue
{
    use Queueable;

    public function handle(RequestFulfilled $event): void
    {
        // Silenced — keeping the listener wired up so it can be re-enabled later.
    }
}
