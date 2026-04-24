<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\IptorrentsSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageIptorrentsSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string $settings = IptorrentsSettings::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $title = 'IPTorrents';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ipt_uid')
                    ->label('UID')
                    ->required(),
                TextInput::make('ipt_pass')
                    ->label('Pass')
                    ->password()
                    ->required(),
            ]);
    }
}
