<?php

use App\Http\Controllers\PlexWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/plex/{token}', PlexWebhookController::class)->name('webhooks.plex');
