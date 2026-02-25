<?php

namespace App\Actions\Request;

use App\Models\Request;
use App\Models\User;

class CreateRequest
{
    public function create(User $user, ?string $notes = null): Request
    {
        return Request::create([
            'user_id' => $user->id,
            'notes' => $notes,
        ]);
    }
}
