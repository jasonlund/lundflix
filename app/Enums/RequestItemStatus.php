<?php

namespace App\Enums;

enum RequestItemStatus: string
{
    case Pending = 'pending';
    case Fulfilled = 'fulfilled';
    case Rejected = 'rejected';
    case NotFound = 'not_found';
}
