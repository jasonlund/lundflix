<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * Create a new user via Plex authentication.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'plex_id' => $input['plex_id'],
            'plex_token' => $input['plex_token'],
            'plex_username' => $input['plex_username'],
            'plex_thumb' => $input['plex_thumb'] ?? null,
        ]);
    }
}
