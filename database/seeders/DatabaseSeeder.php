<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@lundflix.com'],
            [
                'name' => 'lundflix',
                'password' => Hash::make('password'),
                'plex_id' => '217658',
                'plex_username' => 'lundflix',
                'plex_thumb' => 'https://plex.tv/users/6e1e991aa79f07da/avatar?c=1768416028',
                'plex_token' => config('services.plex.seed_token'),
                'role' => UserRole::Admin,
            ]
        );

        $this->call(MovieShowSeeder::class);
    }
}
