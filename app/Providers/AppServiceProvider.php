<?php

namespace App\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Password::defaults(function () {
            $rule = Password::min(8);

            return $this->app->isProduction()
                ? $rule->letters()->mixedCase()->numbers()->symbols()->uncompromised()
                : $rule;
        });

        Http::globalRequestMiddleware(fn ($request) => $request->withHeader(
            'User-Agent', config('app.name').'/1.0 (+'.config('app.url').')'
        ));
    }
}
