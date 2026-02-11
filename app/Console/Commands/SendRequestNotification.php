<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\Request;
use App\Notifications\RequestItemsNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendRequestNotification extends Command
{
    protected $signature = 'slack:send-request {request}';

    protected $description = 'Send a request\'s items to Slack';

    public function handle(): int
    {
        if (! config('services.slack.enabled')) {
            $this->error('Slack is not enabled.');

            return Command::FAILURE;
        }

        $request = Request::with(['items.requestable' => function ($morphTo) {
            $morphTo->morphWith([
                Episode::class => ['show'],
            ]);
        }])->find($this->argument('request'));

        if (! $request) {
            $this->error('Request not found.');

            return Command::FAILURE;
        }

        if ($request->items->isEmpty()) {
            $this->error('Request has no items.');

            return Command::FAILURE;
        }

        $channel = config('services.slack.notifications.channel');

        if (! $channel) {
            $this->error('Slack channel not configured.');

            return Command::FAILURE;
        }

        Notification::route('slack', $channel)
            ->notify(new RequestItemsNotification($request));

        $this->info("Notification sent for request #{$request->id}.");

        return Command::SUCCESS;
    }
}
