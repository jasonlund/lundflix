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

    public function cookieHeader(): string
    {
        return "uid={$this->ipt_uid}; pass={$this->ipt_pass}";
    }

    public function isConfigured(): bool
    {
        return $this->ipt_uid !== '' && $this->ipt_pass !== '';
    }
}
