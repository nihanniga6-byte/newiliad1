<?php
/**
 * Validation Class
 * 
 * Provides form validation with rules and error messages.
 * 
 * @package Core
 */

class Validation
{
    private array $errors = [];

    /**
     * Validate data against rules
     * 
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @return bool True if valid
     */
    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleString) {
            $rulesList = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $data[$field] ?? '';
            $label = ucfirst(str_replace('_', ' ', $field));

            foreach ($rulesList as $rule) {
                $this->applyRule($field, $label, $value, $rule, $data);
            }
        }

        return empty($this->errors);
    }

    /**
     * Apply a single validation rule
     */
    private function applyRule(string $field, string $label, $value, string $rule, array $allData): void
    {
        // Required
        if ($rule === 'required') {
            if (empty($value) && $value !== '0') {
                $this->errors[$field][] = "{$label} is required";
            }
            return;
        }

        // Skip other rules if value is empty and not required
        if (empty($value) && $value !== '0') {
            return;
        }

        // Min length
        if (strpos($rule, 'min:') === 0) {
            $min = (int) substr($rule, 4);
            if (strlen($value) < $min) {
                $this->errors[$field][] = "{$label} must be at least {$min} characters";
            }
        }

        // Max length
        if (strpos($rule, 'max:') === 0) {
            $max = (int) substr($rule, 4);
            if (strlen($value) > $max) {
                $this->errors[$field][] = "{$label} must not exceed {$max} characters";
            }
        }

        // Email
        if ($rule === 'email') {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $this->errors[$field][] = "{$label} must be a valid email address";
            }
        }

        // Numeric
        if ($rule === 'numeric') {
            if (!is_numeric($value)) {
                $this->errors[$field][] = "{$label} must be numeric";
            }
        }

        // Integer
        if ($rule === 'integer') {
            if (!filter_var($value, FILTER_VALIDATE_INT)) {
                $this->errors[$field][] = "{$label} must be an integer";
            }
        }

        // Alpha
        if ($rule === 'alpha') {
            if (!ctype_alpha($value)) {
                $this->errors[$field][] = "{$label} must contain only letters";
            }
        }

        // Alpha numeric
        if ($rule === 'alpha_num') {
            if (!ctype_alnum($value)) {
                $this->errors[$field][] = "{$label} must contain only letters and numbers";
            }
        }

        // Match (password confirmation)
        if (strpos($rule, 'match:') === 0) {
            $matchField = substr($rule, 6);
            $matchValue = $allData[$matchField] ?? '';
            if ($value !== $matchValue) {
                $this->errors[$field][] = "{$label} does not match";
            }
        }

        // Different (must be different from another field)
        if (strpos($rule, 'different:') === 0) {
            $diffField = substr($rule, 10);
            $diffValue = $allData[$diffField] ?? '';
            if ($value === $diffValue) {
                $this->errors[$field][] = "{$label} must be different from " . ucfirst(str_replace('_', ' ', $diffField));
            }
        }

        // In (must be one of the allowed values)
        if (strpos($rule, 'in:') === 0) {
            $allowed = explode(',', substr($rule, 3));
            if (!in_array($value, $allowed)) {
                $this->errors[$field][] = "{$label} must be one of: " . implode(', ', $allowed);
            }
        }

        // URL
        if ($rule === 'url') {
            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                $this->errors[$field][] = "{$label} must be a valid URL";
            }
        }

        // Date
        if ($rule === 'date') {
            $date = DateTime::createFromFormat('Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                $this->errors[$field][] = "{$label} must be a valid date (YYYY-MM-DD)";
            }
        }
    }

    /**
     * Get all errors
     * 
     * @return array Validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get error for specific field
     * 
     * @param string $field Field name
     * @return string|null Error message or null
     */
    public function getError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Check if there are any errors
     * 
     * @return bool True if has errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get first error message
     * 
     * @return string|null First error message or null
     */
    public function getFirstError(): ?string
    {
        foreach ($this->errors as $errors) {
            if (!empty($errors)) {
                return $errors[0];
            }
        }
        return null;
    }
}
