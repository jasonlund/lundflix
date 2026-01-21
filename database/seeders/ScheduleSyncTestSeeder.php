<?php

namespace Database\Seeders;

use App\Models\Episode;
use App\Models\Show;
use Illuminate\Database\Seeder;

class ScheduleSyncTestSeeder extends Seeder
{
    /**
     * Seed shows with episodes for schedule sync testing.
     *
     * Uses real TVMaze IDs for popular currently-airing shows
     * that are likely to have upcoming episodes in the schedule.
     */
    public function run(): void
    {
        // Real TVMaze show IDs for currently-airing shows
        $showsData = [
            ['tvmaze_id' => 59, 'name' => 'Chicago Fire'],
            ['tvmaze_id' => 718, 'name' => 'The Tonight Show Starring Jimmy Fallon'],
            ['tvmaze_id' => 329, 'name' => 'Shark Tank'],
            ['tvmaze_id' => 2756, 'name' => 'The Late Show with Stephen Colbert'],
            ['tvmaze_id' => 8566, 'name' => 'Good Morning America'],
        ];

        foreach ($showsData as $showData) {
            $show = Show::updateOrCreate(
                ['tvmaze_id' => $showData['tvmaze_id']],
                [
                    'name' => $showData['name'],
                    'status' => 'Running',
                    'type' => 'Scripted',
                    'language' => 'English',
                ]
            );

            // Create some existing episodes so the show is "tracked"
            for ($season = 1; $season <= 2; $season++) {
                for ($number = 1; $number <= 5; $number++) {
                    Episode::updateOrCreate(
                        [
                            'show_id' => $show->id,
                            'season' => $season,
                            'number' => $number,
                            'type' => 'regular',
                        ],
                        [
                            'tvmaze_id' => $show->tvmaze_id * 10000 + $season * 100 + $number,
                            'name' => "Episode {$number}",
                            'airdate' => now()->subMonths(6)->addDays(($season - 1) * 30 + $number * 7),
                        ]
                    );
                }
            }
        }

        $this->command->info('Created 5 shows with 10 episodes each for schedule sync testing.');
        $this->command->info('Run `php artisan tvmaze:sync-schedule` to test the sync.');
    }
}
