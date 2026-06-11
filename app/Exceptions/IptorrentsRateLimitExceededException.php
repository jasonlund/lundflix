<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class IptorrentsRateLimitExceededException extends RuntimeException
{
    public function __construct(public int $retryAfter = 60)
    {
        parent::__construct('IPTorrents rate limit exceeded.');
    }
}
