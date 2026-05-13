<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;
use Spatie\LaravelSettings\Settings;

class IptorrentsSettings extends Settings
{
    public string $ipt_uid = '';

    #[ShouldBeEncrypted]
    public string $ipt_pass = '';

    public static function group(): string
    {
        return 'iptorrents';
    }

    public function resolvedUid(): string
    {
        return $this->ipt_uid !== '' ? $this->ipt_uid : (string) config('services.iptorrents.uid', '');
    }

    public function resolvedPass(): string
    {
        return $this->ipt_pass !== '' ? $this->ipt_pass : (string) config('services.iptorrents.pass', '');
    }

    public function cookieHeader(): string
    {
        return "uid={$this->resolvedUid()}; pass={$this->resolvedPass()}";
    }

    public function isConfigured(): bool
    {
        return $this->resolvedUid() !== '' && $this->resolvedPass() !== '';
    }
}
