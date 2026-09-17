<?php
/**
 * Base Controller Class
 * 
 * Provides common functionality for all controllers including:
 * - View rendering with layout
 * - Redirect handling
 * - Flash messages
 * - Input handling
 * - Validation helpers
 * 
 * @package Core
 */

class Controller
{
    protected Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Load a view with optional layout
     * 
     * @param string $view View path (e.g., 'dashboard/index')
     * @param array $data Data to pass to view
     * @param bool $useLayout Whether to use master layout
     */
    protected function view(string $view, array $data = [], bool $useLayout = true): void
    {
        // Extract data to make variables available in view
        extract($data);

        $viewPath = ROOT_PATH . '/app/views/' . $view . '.php';

        if (!file_exists($viewPath)) {
            Logger::error("View not found: {$view}");
            require ROOT_PATH . '/app/views/errors/404.php';
            return;
        }

        if ($useLayout) {
            // Start output buffering
            ob_start();
            require $viewPath;
            $content = ob_get_clean();

            // Load layout with content
            require ROOT_PATH . '/app/views/layouts/main.php';
        } else {
            require $viewPath;
        }
    }

    /**
     * Load a partial view (without layout)
     * 
     * @param string $view View path
     * @param array $data Data to pass to view
     */
    protected function viewPartial(string $view, array $data = []): void
    {
        extract($data);
        $viewPath = ROOT_PATH . '/app/views/' . $view . '.php';
        
        if (file_exists($viewPath)) {
            require $viewPath;
        }
    }

    /**
     * Redirect to URL
     * 
     * @param string $url Target URL
     * @param int $statusCode HTTP status code
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        header("Location: {$url}", true, $statusCode);
        exit;
    }

    /**
     * Send JSON response
     * 
     * @param array $data Data to encode as JSON
     * @param int $statusCode HTTP status code
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Get POST data safely
     * 
     * @param string $key POST key
     * @param mixed $default Default value
     * @return mixed Sanitized value
     */
    protected function input(string $key, $default = null)
    {
        if (!isset($_POST[$key])) {
            return $default;
        }
        
        $value = $_POST[$key];
        
        // Auto-sanitize strings
        if (is_string($value)) {
            return Security::sanitize($value);
        }
        
        return $value;
    }

    /**
     * Get GET data safely
     * 
     * @param string $key GET key
     * @param mixed $default Default value
     * @return mixed Sanitized value
     */
    protected function query(string $key, $default = null)
    {
        if (!isset($_GET[$key])) {
            return $default;
        }
        
        $value = $_GET[$key];
        
        if (is_string($value)) {
            return Security::sanitize($value);
        }
        
        return $value;
    }

    /**
     * Get URL segment
     * 
     * @param int $index Segment index (0-based)
     * @param mixed $default Default value
     * @return mixed Segment value
     */
    protected function segment(int $index, $default = null)
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', $uri)));
        
        return $segments[$index] ?? $default;
    }

    /**
     * Set flash message
     * 
     * @param string $type Message type (success, error, warning, info)
     * @param string $message Message text
     */
    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    /**
     * Validate form data
     * 
     * @param array $rules Validation rules
     * @param array $data Data to validate
     * @return array Array of validation errors
     */
    protected function validate(array $rules, array $data): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $rulesList = explode('|', $ruleString);
            $value = $data[$field] ?? '';
            $label = ucfirst(str_replace('_', ' ', $field));

            foreach ($rulesList as $rule) {
                // Required
                if ($rule === 'required' && empty($value) && $value !== '0') {
                    $errors[$field][] = "{$label} is required";
                }

                // Min length
                if (strpos($rule, 'min:') === 0) {
                    $min = (int) substr($rule, 4);
                    if (strlen($value) < $min) {
                        $errors[$field][] = "{$label} must be at least {$min} characters";
                    }
                }

                // Max length
                if (strpos($rule, 'max:') === 0) {
                    $max = (int) substr($rule, 4);
                    if (strlen($value) > $max) {
                        $errors[$field][] = "{$label} must not exceed {$max} characters";
                    }
                }

                // Email
                if ($rule === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "{$label} must be a valid email address";
                }

                // Numeric
                if ($rule === 'numeric' && !empty($value) && !is_numeric($value)) {
                    $errors[$field][] = "{$label} must be numeric";
                }

                // Match (password confirmation)
                if (strpos($rule, 'match:') === 0) {
                    $matchField = substr($rule, 6);
                    $matchValue = $data[$matchField] ?? '';
                    if ($value !== $matchValue) {
                        $errors[$field][] = "{$label} does not match";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Check if validation has errors
     * 
     * @param array $errors Validation errors
     * @return bool True if there are errors
     */
    protected function hasErrors(array $errors): bool
    {
        return !empty($errors);
    }

    /**
     * Load a model
     * 
     * @param string $modelName Model class name
     * @return object Model instance
     */
    protected function model(string $modelName): object
    {
        $modelFile = ROOT_PATH . '/app/models/' . $modelName . '.php';
        
        if (file_exists($modelFile)) {
            require_once $modelFile;
        }
        
        if (class_exists($modelName)) {
            return new $modelName();
        }
        
        throw new Exception("Model not found: {$modelName}");
    }
}
