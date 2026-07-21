<?php

namespace VibeBoard\Router;

/**
 * Minimal HTTP router.
 *
 * Supports exact and parameterised routes. Routes are matched in the order
 * they are added.
 */
class Router
{
    /** @var array<int, array{method: string, pattern: string, callback: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $callback): self
    {
        return $this->add('GET', $pattern, $callback);
    }

    public function post(string $pattern, callable $callback): self
    {
        return $this->add('POST', $pattern, $callback);
    }

    public function put(string $pattern, callable $callback): self
    {
        return $this->add('PUT', $pattern, $callback);
    }

    public function delete(string $pattern, callable $callback): self
    {
        return $this->add('DELETE', $pattern, $callback);
    }

    public function add(string $method, string $pattern, callable $callback): self
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'callback' => $callback,
        ];
        return $this;
    }

    /**
     * Dispatch the current request.
     *
     * @param string|null $method Override HTTP method (useful in tests).
     * @param string|null $path Override request path (useful in tests).
     */
    public function dispatch(?string $method = null, ?string $path = null): void
    {
        $method = $method ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $path ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            $params = $this->match($route['pattern'], $path);
            if ($params === null) {
                continue;
            }

            $response = ($route['callback'])($params);

            if (is_array($response) || is_object($response)) {
                header('Content-Type: application/json');
                echo json_encode($response);
            } else {
                echo $response;
            }
            return;
        }

        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not found']);
    }

    /**
     * @return array<string, string>|null
     */
    private function match(string $pattern, string $path): ?array
    {
        $pattern = rtrim($pattern, '/') ?: '/';
        $pattern = '#^' . preg_replace('#:([a-zA-Z_][a-zA-Z0-9_]*)#', '(?P\u003c$1\u003e[^/]+)', $pattern) . '$#';

        if (!preg_match($pattern, $path, $matches)) {
            return null;
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
