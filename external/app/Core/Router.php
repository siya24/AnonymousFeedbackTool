<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable|array $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $regex = '#^' . preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $route['pattern']) . '$#';
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }

            $params = array_filter($matches, static fn ($key): bool => !is_int($key), ARRAY_FILTER_USE_KEY);
            $handler = $route['handler'];

            if (is_array($handler) && is_string($handler[0])) {
                $class = $handler[0];
                $methodName = $handler[1];
                (new $class())->{$methodName}($params);
                return;
            }

            $handler($params);
            return;
        }

        Response::json(['error' => 'Route not found'], 404);
    }
}
