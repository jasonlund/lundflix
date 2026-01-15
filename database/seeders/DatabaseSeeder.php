<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'user@lundflix.com'],
            [
                'name' => 'lundflix',
                'plex_id' => '217658',
                'plex_username' => 'lundflix',
                'plex_thumb' => 'https://plex.tv/users/6e1e991aa79f07da/avatar?c=1768416028',
                'plex_token' => '-Bkxg2g4MCyUy6T8XiDc',
            ]
        );

        $this->call(MovieShowSeeder::class);
    }
}
