<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ErrorPageResolver;
use Illuminate\Http\Response;

class ErrorPreviewController
{
    public function __invoke(int $status): Response
    {
        $page = ErrorPageResolver::resolve($status);

        if (! $page) {
            abort(404);
        }

        return response()->view('errors.error', [
            ...$page,
            'traceId' => null,
        ], $status);
    }
}
