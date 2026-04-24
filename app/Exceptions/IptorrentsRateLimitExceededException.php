<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class IptorrentsRateLimitExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('IPTorrents rate limit exceeded.');
    }
}
