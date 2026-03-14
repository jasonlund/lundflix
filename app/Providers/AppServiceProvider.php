<?php

namespace App\Providers;

use App\Support\ErrorPageResolver;
use Illuminate\Database\Eloquent\Model;
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
        //
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

        Vite::macro('image', fn (string $asset) => Vite::asset("resources/images/{$asset}"));

        Blaze::optimize();

        View::composer('components.layouts.app', function ($view) {
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
