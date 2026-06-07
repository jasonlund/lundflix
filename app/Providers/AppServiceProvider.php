<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Torrent\Finders\EpisodeByImdbFinder;
use App\Services\Torrent\Finders\EpisodeByNameFinder;
use App\Services\Torrent\Finders\MovieByForeignTitleFinder;
use App\Services\Torrent\Finders\MovieByImdbFinder;
use App\Services\Torrent\Finders\MovieByNameFinder;
use App\Services\Torrent\Finders\SeasonPackByImdbFinder;
use App\Services\Torrent\Finders\SeasonPackByNameFinder;
use App\Services\Torrent\TorrentResolver;
use App\Support\ErrorPageResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Blaze\Blaze;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TorrentResolver::class, fn ($app): TorrentResolver => new TorrentResolver([
            $app->make(MovieByImdbFinder::class),
            $app->make(MovieByNameFinder::class),
            $app->make(MovieByForeignTitleFinder::class),
            $app->make(EpisodeByImdbFinder::class),
            $app->make(EpisodeByNameFinder::class),
            $app->make(SeasonPackByImdbFinder::class),
            $app->make(SeasonPackByNameFinder::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();

        Password::defaults(function () {
            $rule = Password::min(8);

            return $this->app->isProduction()
                ? $rule->letters()->mixedCase()->numbers()->symbols()->uncompromised()
                : $rule;
        });

        Http::globalRequestMiddleware(fn ($request) => $request->withHeader(
            'User-Agent', config('app.name').'/1.0 (+'.config('app.url').')'
        ));

        Http::macro('resilient', fn () => Http::retry(
            3,
            1000,
            when: fn ($e): bool => $e instanceof ConnectionException
                || ($e instanceof RequestException && in_array($e->response->status(), [408, 429, 502, 503, 504])),
        ));

        Vite::macro('image', fn (string $asset) => Vite::asset("resources/images/{$asset}"));

        Blaze::optimize();

        View::composer('components.layouts.app', function ($view): void {
            $data = $view->getData();
            $defaultBackground = Vite::image('default-background.svg');

            $view->with([
                'defaultBackground' => $defaultBackground,
                'backgroundImage' => $data['backgroundImage'] ?? $defaultBackground,
                'errorPages' => $data['errorPages'] ?? ErrorPageResolver::all(),
            ]);
        });
    }
}
