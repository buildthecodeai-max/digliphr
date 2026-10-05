<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use Closure;

class Router
{
    private array $routes = [];
    private array $groupStack = [];

    public function __construct(
        private readonly Request $request,
        private readonly Response $response
    ) {
    }

    public function get(string $uri, array|string|Closure $action, array $middleware = []): self
    {
        return $this->addRoute('GET', $uri, $action, $middleware);
    }

    public function post(string $uri, array|string|Closure $action, array $middleware = []): self
    {
        return $this->addRoute('POST', $uri, $action, $middleware);
    }

    public function put(string $uri, array|string|Closure $action, array $middleware = []): self
    {
        return $this->addRoute('PUT', $uri, $action, $middleware);
    }

    public function patch(string $uri, array|string|Closure $action, array $middleware = []): self
    {
        return $this->addRoute('PATCH', $uri, $action, $middleware);
    }

    public function delete(string $uri, array|string|Closure $action, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $uri, $action, $middleware);
    }

    public function group(array $attributes, Closure $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    private function addRoute(string $method, string $uri, array|string|Closure $action, array $middleware = []): self
    {
        $prefix = '';
        $groupMiddleware = [];
        $namePrefix = '';

        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'] ?? '';
            $groupMiddleware = array_merge($groupMiddleware, $group['middleware'] ?? []);
            $namePrefix .= $group['as'] ?? '';
        }

        $uri = '/' . trim($prefix . '/' . trim($uri, '/'), '/');
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        $this->routes[] = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action,
            'middleware' => array_merge($groupMiddleware, $middleware),
            'name' => null,
            'name_prefix' => $namePrefix,
        ];

        return $this;
    }

    public function name(string $name): self
    {
        $last = array_key_last($this->routes);
        if ($last !== null) {
            $prefix = $this->routes[$last]['name_prefix'] ?? '';
            $this->routes[$last]['name'] = $prefix . $name;
        }

        return $this;
    }

    public function dispatch(): void
    {
        $method = $this->request->method();
        $uri = $this->request->uri();

        foreach ($this->routes as $route) {
            $params = $this->match($route['uri'], $uri);
            if ($params === false || $route['method'] !== $method) {
                continue;
            }

            $this->request->setRouteParams($params);
            $handler = $this->resolveMiddleware($route['middleware'], function () use ($route, $params) {
                return $this->runAction($route['action'], $params);
            });

            $result = $handler();
            if ($result !== null) {
                $this->response->send($result);
            }
            return;
        }

        throw new HttpException('Page not found.', 404);
    }

    private function match(string $routeUri, string $requestUri): array|false
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $routeUri);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $requestUri, $matches)) {
            return false;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (!is_int($key)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    private function resolveMiddleware(array $middleware, Closure $destination): Closure
    {
        $pipeline = array_reduce(
            array_reverse($middleware),
            function ($next, $middlewareClass) {
                return function () use ($middlewareClass, $next) {
                    $instance = new $middlewareClass();
                    return $instance->handle($this->request, $this->response, $next);
                };
            },
            $destination
        );

        return $pipeline;
    }

    private function runAction(array|string|Closure $action, array $params): mixed
    {
        if ($action instanceof Closure) {
            return $action($this->request, $this->response, ...array_values($params));
        }

        if (is_string($action)) {
            [$controller, $method] = explode('@', $action);
        } else {
            [$controller, $method] = $action;
        }

        // Routes use short names like Auth\AuthController or Admin\DashboardController.
        if (!str_starts_with((string) $controller, 'App\\')) {
            $controller = "App\\Controllers\\{$controller}";
        }

        if (!class_exists($controller)) {
            throw new HttpException("Controller {$controller} not found.", 500);
        }

        $instance = new $controller($this->request, $this->response);

        if (!method_exists($instance, $method)) {
            throw new HttpException("Method {$method} not found on {$controller}.", 500);
        }

        $args = array_map(
            static fn (mixed $value): mixed => is_string($value) && ctype_digit($value)
                ? (int) $value
                : $value,
            array_values($params)
        );

        return $instance->{$method}(...$args);
    }

    public function url(string $name, array $params = []): string
    {
        foreach ($this->routes as $route) {
            if (($route['name'] ?? null) !== $name) {
                continue;
            }

            $uri = $route['uri'];
            foreach ($params as $key => $value) {
                $uri = str_replace('{' . $key . '}', (string) $value, $uri);
            }

            return $uri;
        }

        return '#';
    }
}
