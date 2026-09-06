<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Infrastructure\TokenService;
use App\Support\HttpException;

final class RequireAuth implements Middleware
{
    public function __construct(private readonly TokenService $tokens)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            throw HttpException::unauthorized();
        }

        $identity = $this->tokens->verify($token);

        if ($identity === null) {
            throw HttpException::unauthorized();
        }

        return $next($request->withIdentity($identity));
    }
}
