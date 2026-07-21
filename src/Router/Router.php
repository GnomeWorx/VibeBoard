<?php

declare(strict_types=1);

namespace VibeBoard\Router;

use VibeBoard\Core\ErrorHandler;

class Router {
    private array $routes = [];

    public function addRoute(string $method, string $path, callable $handler): void {
        $this->routes[$method][$path] = $handler;
    }

    public function resolve(): void {
        try {
            $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $method = $_SERVER['REQUEST_METHOD'];

            // Exact match first
            if (isset($this->routes[$method][$requestUri])) {
                $handler = $this->routes[$method][$requestUri];
                call_user_func($handler);
                return;
            }

            // Parameterised match: /api/tasks/{id}
            foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
                $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
                $regex = '#^' . $regex . '$#';
                if (preg_match($regex, $requestUri, $matches)) {
                    // Extract named params into $_REQUEST
                    foreach ($matches as $key => $value) {
                        if (is_string($key)) {
                            $_REQUEST[$key] = $value;
                        }
                    }
                    call_user_func($handler);
                    return;
                }
            }

            // No match found
            ErrorHandler::handle(new \Exception("Route not found: $requestUri"), "Router");
        } catch (\Exception $e) {
            ErrorHandler::handle($e, "Routing Execution");
        }
    }
}
