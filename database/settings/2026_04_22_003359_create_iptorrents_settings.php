<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('iptorrents.ipt_uid', '');
        $this->migrator->add('iptorrents.ipt_pass', '', encrypted: true);
    }
};
