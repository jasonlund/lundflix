<?php

use App\Support\ErrorPageResolver;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Nightwatch\Compatibility;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->context(function () {
            $traceId = class_exists(Compatibility::class)
                ? Compatibility::getTraceIdFromContext()
                : null;

            return $traceId ? ['nightwatch_trace_id' => $traceId] : [];
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (app()->hasDebugModeEnabled()) {
                return null;
            }

            if ($e instanceof AuthenticationException || $e instanceof ValidationException) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $page = ErrorPageResolver::resolve($status);

            if (! $page) {
                return null;
            }

            $traceId = class_exists(Compatibility::class)
                ? Compatibility::getTraceIdFromContext()
                : null;

            return response()->view('errors.error', [
                ...$page,
                'traceId' => $traceId,
            ], $status);
        });

        $exceptions->respond(function ($response) {
            $traceId = class_exists(Compatibility::class)
                ? Compatibility::getTraceIdFromContext()
                : null;

            if ($traceId && $response instanceof \Symfony\Component\HttpFoundation\Response) {
                $response->headers->set('X-Trace-Id', $traceId);
            }

            return $response;
        });
    })->create();
