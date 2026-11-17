<?php
namespace App\Core;

class Router {
    private array $routes = [];
    private Request $request;
    private Response $response;

    public function __construct(Request $request, Response $response) {
        $this->request = $request;
        $this->response = $response;
    }

    public function get(string $path, $handler, array $middlewares = []): self {
        $this->addRoute('GET', $path, $handler, $middlewares);
        return $this;
    }

    public function post(string $path, $handler, array $middlewares = []): self {
        $this->addRoute('POST', $path, $handler, $middlewares);
        return $this;
    }

    private function addRoute(string $method, string $path, $handler, array $middlewares = []): void {
        $path = '/' . trim($path, '/');
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $middlewares
        ];
    }

    /**
     * Resolve middleware string / array to executable instance
     */
    private function resolveMiddleware($middleware) {
        if (is_callable($middleware)) {
            return $middleware;
        }

        if (is_object($middleware)) {
            return $middleware;
        }

        if (is_string($middleware)) {
            // Check for parameterized middleware (e.g. 'role:owner_platform,admin_owner')
            if (strpos($middleware, ':') !== false) {
                list($alias, $paramStr) = explode(':', $middleware, 2);
                $params = array_map('trim', explode(',', $paramStr));
                if ($alias === 'role') {
                    return new \App\Middlewares\RoleMiddleware($params);
                }
            }

            // Simple aliases
            if ($middleware === 'auth') {
                return new \App\Middlewares\AuthMiddleware();
            }
            if ($middleware === 'guest') {
                return new \App\Middlewares\GuestMiddleware();
            }

            if (class_exists($middleware)) {
                return new $middleware();
            }

            $namespaced = "\\App\\Middlewares\\" . $middleware;
            if (class_exists($namespaced)) {
                return new $namespaced();
            }
        }

        return null;
    }

    public function dispatch(): void {
        $requestMethod = $this->request->getMethod();
        $requestPath = $this->request->getPath();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $requestPath, $matches)) {
                array_shift($matches); // Remove full match

                // Execute middlewares
                foreach ($route['middlewares'] as $middlewareDef) {
                    $middleware = $this->resolveMiddleware($middlewareDef);
                    if ($middleware !== null) {
                        if (is_callable($middleware)) {
                            call_user_func($middleware, $this->request, $this->response);
                        } elseif (is_object($middleware) && method_exists($middleware, 'handle')) {
                            $middleware->handle($this->request, $this->response);
                        }
                    }
                }

                $handler = $route['handler'];

                if (is_callable($handler)) {
                    call_user_func_array($handler, array_merge([$this->request, $this->response], $matches));
                    return;
                }

                if (is_array($handler) && count($handler) === 2) {
                    list($controllerClass, $method) = $handler;
                    $controller = new $controllerClass();
                    call_user_func_array([$controller, $method], array_merge([$this->request, $this->response], $matches));
                    return;
                }

                if (is_string($handler) && strpos($handler, '@') !== false) {
                    list($controllerClass, $method) = explode('@', $handler);
                    $fullClass = "\\App\\Controllers\\" . $controllerClass;
                    if (class_exists($fullClass)) {
                        $controller = new $fullClass();
                        call_user_func_array([$controller, $method], array_merge([$this->request, $this->response], $matches));
                        return;
                    }
                }
            }
        }

        // 404 Not Found
        $this->response->setStatusCode(404);
        if ($this->request->isAjax()) {
            $this->response->json(['status' => 'error', 'message' => 'Endpoint tidak ditemukan.'], 404);
        } else {
            View::render('errors/404', ['title' => '404 - Halaman Tidak Ditemukan'], 'layouts/main');
        }
    }
}
