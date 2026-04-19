<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class PreDBRateLimitExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('PreDB API rate limit exceeded.');
    }
}
