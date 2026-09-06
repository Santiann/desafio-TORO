<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\Middleware;
use App\Support\HttpException;

final class Router
{
    /**
     * @var Route[]
     */
    private array $routes = [];

    /**
     * @param callable(Request): Response $handler
     * @param Middleware[] $middleware
     */
    public function get(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->routes[] = new Route('GET', $pattern, $handler, $middleware);
    }

    /**
     * @param callable(Request): Response $handler
     * @param Middleware[] $middleware
     */
    public function post(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->routes[] = new Route('POST', $pattern, $handler, $middleware);
    }

    /**
     * @param callable(Request): Response $handler
     * @param Middleware[] $middleware
     */
    public function put(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->routes[] = new Route('PUT', $pattern, $handler, $middleware);
    }

    /**
     * @param callable(Request): Response $handler
     * @param Middleware[] $middleware
     */
    public function delete(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->routes[] = new Route('DELETE', $pattern, $handler, $middleware);
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route->regex, $request->path, $matches) !== 1) {
                continue;
            }

            $pathMatched = true;

            if ($route->method !== $request->method) {
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            return $this->pipeline($route, $request->withParams($params));
        }

        throw $pathMatched ? HttpException::methodNotAllowed() : HttpException::notFound();
    }

    private function pipeline(Route $route, Request $request): Response
    {
        $handler = $route->handler;
        $next = static fn (Request $carried): Response => $handler($carried);

        foreach (array_reverse($route->middleware) as $middleware) {
            $next = static fn (Request $carried): Response => $middleware->handle($carried, $next);
        }

        return $next($request);
    }
}
