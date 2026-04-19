<?php

declare(strict_types=1);

use App\Providers\Filament\AdminPanelProvider;

arch()->preset()->php();
arch()->preset()->security()
    ->ignoring('Database\Seeders')
    ->ignoring('App\Services\ThirdParty\IMDBService')
    ->ignoring('App\Filament');
arch()->preset()->laravel()
    ->ignoring(AdminPanelProvider::class)
    ->ignoring('App\Console\Commands');

arch('app uses strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('app does not use env() helper')
    ->expect('env')
    ->not->toBeUsed()
    ->ignoring('App\Providers');

arch('notifications extend base class')
    ->expect('App\Notifications')
    ->toExtend('Illuminate\Notifications\Notification');
