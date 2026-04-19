<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Models\Episode;
use App\Models\Request;
use App\Notifications\RequestProcessedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendRequestFulfilledNotification extends Command
{
    protected $signature = 'slack:send-request-fulfilled {request}';

    protected $description = 'Send a fulfilled request\'s items to Slack';

    public function handle(): int
    {
        if (! config('services.slack.enabled')) {
            $this->error('Slack is not enabled.');

            return Command::FAILURE;
        }

        $request = Request::with(['items.requestable' => function ($morphTo): void {
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

        if ($request->status !== RequestStatus::Fulfilled) { // @phpstan-ignore property.notFound (computed attribute)
            $this->error('Request is not fulfilled.');

            return Command::FAILURE;
        }

        $channel = config('services.slack.notifications.channel');

        if (! $channel) {
            $this->error('Slack channel not configured.');

            return Command::FAILURE;
        }

        Notification::route('slack', $channel)
            ->notify(new RequestProcessedNotification($request));

        $this->info("Notification sent for request #{$request->id}.");

        return Command::SUCCESS;
    }
}
