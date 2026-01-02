<?php

namespace App;

class Router
{
    private array $routes = [];
    private array $middlewares = [];
    private string $basePath = '';

    public function setBasePath(string $path): self
    {
        $this->basePath = rtrim($path, '/');
        return $this;
    }

    public function get(string $path, callable|array $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    public function addRoute(string $method, string $path, callable|array $handler): self
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
        ];
        return $this;
    }

    public function middleware(callable $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function dispatch(): mixed
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $this->getUri();

        // Handle preflight requests for CORS
        if ($method === 'OPTIONS') {
            return null;
        }

        // Find matching route
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchPath($route['path'], $uri);
            if ($params !== false) {
                // Run middlewares
                foreach ($this->middlewares as $middleware) {
                    $result = $middleware($params);
                    if ($result !== null) {
                        return $result;
                    }
                }

                // Call handler
                return $this->callHandler($route['handler'], $params);
            }
        }

        // No route found
        http_response_code(404);
        return ['error' => 'Not Found', 'code' => 'NOT_FOUND'];
    }

    private function getUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // Remove base path
        if ($this->basePath && str_starts_with($uri, $this->basePath)) {
            $uri = substr($uri, strlen($this->basePath));
        }

        // Ensure leading slash
        if (!str_starts_with($uri, '/')) {
            $uri = '/' . $uri;
        }

        // Remove trailing slash (except for root)
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        return $uri;
    }

    /**
     * Match a route path against a URI
     * Returns parameters array on match, false otherwise
     *
     * Supports:
     * - Literal segments: /api/votes
     * - Parameters: /api/votes/:id
     * - Optional parameters: /api/votes/:id?
     */
    private function matchPath(string $routePath, string $uri): array|false
    {
        $routeParts = explode('/', trim($routePath, '/'));
        $uriParts = explode('/', trim($uri, '/'));

        // Handle empty paths
        if ($routePath === '/' && $uri === '/') {
            return [];
        }

        $params = [];
        $routeIndex = 0;
        $uriIndex = 0;

        while ($routeIndex < count($routeParts)) {
            $routePart = $routeParts[$routeIndex];
            $isOptional = str_ends_with($routePart, '?');
            $isParam = str_starts_with($routePart, ':');

            if ($isOptional) {
                $routePart = substr($routePart, 0, -1);
            }

            if ($uriIndex >= count($uriParts) || $uriParts[$uriIndex] === '') {
                // No more URI parts
                if ($isOptional) {
                    $routeIndex++;
                    continue;
                }
                return false;
            }

            $uriPart = $uriParts[$uriIndex];

            if ($isParam) {
                // Parameter - extract value
                $paramName = substr($routePart, 1);
                $params[$paramName] = urldecode($uriPart);
            } elseif ($routePart !== $uriPart) {
                // Literal mismatch
                return false;
            }

            $routeIndex++;
            $uriIndex++;
        }

        // Check if all URI parts were consumed
        if ($uriIndex < count($uriParts) && $uriParts[$uriIndex] !== '') {
            return false;
        }

        return $params;
    }

    private function callHandler(callable|array $handler, array $params): mixed
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            if (is_string($class)) {
                $class = new $class();
            }
            return $class->$method($params);
        }

        return $handler($params);
    }

    /**
     * Get JSON body from request
     */
    public static function getJsonBody(): ?array
    {
        $body = file_get_contents('php://input');
        if (empty($body)) {
            return null;
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Send JSON response
     */
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Send error response
     */
    public static function error(string $message, string $code, int $status = 400): void
    {
        self::json(['error' => $message, 'code' => $code], $status);
    }

    /**
     * Redirect to a URL
     */
    public static function redirect(string $url): void
    {
        header('Location: ' . $url);
        if (!defined('PHPUNIT_RUNNING')) {
            exit;
        }
    }
}
