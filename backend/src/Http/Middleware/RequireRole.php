<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Role;
use App\Http\Request;
use App\Http\Response;
use App\Support\HttpException;

final class RequireRole implements Middleware
{
    public function __construct(private readonly Role $role)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($request->identity()->role !== $this->role) {
            throw HttpException::forbidden();
        }

        return $next($request);
    }
}
