<?php

use App\Support\DatabaseRetry;
use Illuminate\Database\QueryException;

it('returns the callback result on success', function () {
    $result = DatabaseRetry::run(fn () => 42);

    expect($result)->toBe(42);
});

it('retries on transient connection errors', function () {
    $attempts = 0;

    $result = DatabaseRetry::run(function () use (&$attempts) {
        $attempts++;

        if ($attempts < 2) {
            throw new QueryException('mysql', 'SELECT 1', [], new \PDOException('Connection refused', 2002));
        }

        return 'success';
    });

    expect($result)->toBe('success')
        ->and($attempts)->toBe(2);
});

it('retries on mysql server gone away', function () {
    $attempts = 0;

    $result = DatabaseRetry::run(function () use (&$attempts) {
        $attempts++;

        if ($attempts < 2) {
            throw new QueryException('mysql', 'SELECT 1', [], new \PDOException('MySQL server has gone away', 2006));
        }

        return 'success';
    });

    expect($result)->toBe('success')
        ->and($attempts)->toBe(2);
});

it('does not retry non-transient query exceptions', function () {
    DatabaseRetry::run(function () {
        throw new QueryException('mysql', 'SELECT 1', [], new \PDOException('Syntax error', 1064));
    });
})->throws(QueryException::class);

it('does not retry non-query exceptions', function () {
    DatabaseRetry::run(function () {
        throw new \RuntimeException('Something else');
    });
})->throws(\RuntimeException::class);

it('throws after exhausting retries', function () {
    DatabaseRetry::run(function () {
        throw new QueryException('mysql', 'SELECT 1', [], new \PDOException('Connection refused', 2002));
    }, times: 2);
})->throws(QueryException::class);
