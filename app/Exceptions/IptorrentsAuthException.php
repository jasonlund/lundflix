<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class IptorrentsAuthException extends RuntimeException
{
    public function __construct(string $message = 'IPTorrents cookie has expired. Update credentials in admin Settings → IPTorrents.')
    {
        parent::__construct($message);
    }
}
