<?php
/**
 * Router Class
 * 
 * Handles URL routing with support for:
 * - GET and POST routes
 * - Dynamic segments (e.g., /user/{id})
 * - Middleware (authentication, admin, CSRF)
 * - 404 handling
 * 
 * @package Core
 */

class Router
{
    private array $routes = [];
    private array $getRoutes = [];
    private array $postRoutes = [];
    private array $middleware = [
        'auth' => AuthMiddleware::class,
        'admin' => AdminMiddleware::class,
        'csrf' => CSRFMiddleware::class,
    ];

    /**
     * Register a GET route
     * 
     * @param string $path URL pattern
     * @param string $handler Controller@method
     * @param array $middlewareNames Middleware to apply
     */
    public function get(string $path, string $handler, array $middlewareNames = []): void
    {
        $this->getRoutes[$path] = [
            'handler' => $handler,
            'middleware' => $middlewareNames
        ];
    }

    /**
     * Register a POST route
     * 
     * @param string $path URL pattern
     * @param string $handler Controller@method
     * @param array $middlewareNames Middleware to apply
     */
    public function post(string $path, string $handler, array $middlewareNames = []): void
    {
        $this->postRoutes[$path] = [
            'handler' => $handler,
            'middleware' => $middlewareNames
        ];
    }

    /**
     * Dispatch the request to the appropriate controller
     * 
     * Matches the current URL against registered routes
     * and executes the handler with middleware.
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';

        // Get routes for current method
        $routes = ($method === 'POST') ? $this->postRoutes : $this->getRoutes;

        // Try exact match first
        if (isset($routes[$uri])) {
            $this->executeRoute($routes[$uri], []);
            return;
        }

        // Try pattern match with dynamic segments
        foreach ($routes as $pattern => $route) {
            $params = $this->matchPattern($pattern, $uri);
            if ($params !== false) {
                $this->executeRoute($route, $params);
                return;
            }
        }

        // 404 Not Found
        $this->handle404($uri);
    }

    /**
     * Match URL pattern against actual URI
     * 
     * Supports patterns like:
     * - /user/{id}
     * - /admin/users/edit/{id}
     * 
     * @param string $pattern Route pattern
     * @param string $uri Actual URI
     * @return array|false Matched parameters or false
     */
    private function matchPattern(string $pattern, string $uri): array|false
    {
        // Convert {param} to named capture groups
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            // Filter out numeric keys
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return false;
    }

    /**
     * Execute a route with middleware
     * 
     * @param array $route Route configuration
     * @param array $params URL parameters
     */
    private function executeRoute(array $route, array $params): void
    {
        // Apply middleware
        foreach ($route['middleware'] as $middlewareName) {
            if (isset($this->middleware[$middlewareName])) {
                $middlewareClass = $this->middleware[$middlewareName];
                $middleware = new $middlewareClass();
                
                if (!$middleware->handle()) {
                    return; // Middleware stopped execution (redirect, etc.)
                }
            }
        }

        // Parse handler (e.g., "UserController@index")
        [$controllerName, $method] = explode('@', $route['handler']);

        // Check if it's a namespaced controller (e.g., "Admin\UserController")
        if (strpos($controllerName, '\\') !== false) {
            $controllerName = ltrim($controllerName, '\\');
        }

        // Add URL parameters to request
        $_REQUEST = array_merge($_REQUEST, $params);

        // Instantiate controller and call method
        $controllerFile = ROOT_PATH . '/app/controllers/' . str_replace('\\', '/', $controllerName) . '.php';
        
        if (!file_exists($controllerFile)) {
            Logger::error("Controller file not found: {$controllerFile}");
            $this->handle404($_SERVER['REQUEST_URI']);
            return;
        }

        require_once $controllerFile;

        if (!class_exists($controllerName)) {
            Logger::error("Controller class not found: {$controllerName}");
            $this->handle404($_SERVER['REQUEST_URI']);
            return;
        }

        $controller = new $controllerName();

        if (!method_exists($controller, $method)) {
            Logger::error("Method not found: {$controllerName}@{$method}");
            $this->handle404($_SERVER['REQUEST_URI']);
            return;
        }

        // Call the method with parameters
        call_user_func_array([$controller, $method], $params);
    }

    /**
     * Handle 404 Not Found
     * 
     * @param string $uri Requested URI
     */
    private function handle404(string $uri): void
    {
        http_response_code(404);
        
        Logger::error("404 Not Found", [
            'uri' => $uri,
            'referrer' => $_SERVER['HTTP_REFERER'] ?? 'direct'
        ]);

        // Load 404 view
        $viewFile = ROOT_PATH . '/app/views/errors/404.php';
        
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo '<!DOCTYPE html><html><head><title>404</title></head>';
            echo '<body><h1>404 - Page Not Found</h1>';
            echo '<p>The page you are looking for could not be found.</p>';
            echo '<a href="/">Go to Homepage</a></body></html>';
        }
        
        exit;
    }
}
