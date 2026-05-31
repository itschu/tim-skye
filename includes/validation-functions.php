<?php
// Prevent direct access
if (!defined('ROOT')) {
    die('Direct access denied');
}

// Load bootstrap if not already loaded
if (!defined('INCLUDES_PATH')) {
    require_once __DIR__ . '/bootstrap.php';
}

/**
 * Validation functions for the investment platform
 */

/**
 * Validate email address
 * @param string $email Email to validate
 * @return bool Validation result
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate monetary amount
 * @param mixed $amount Amount to validate
 * @param float $min Minimum value
 * @param float|null $max Maximum value (optional)
 * @return bool Validation result
 */
function validate_amount($amount, $min = 0, $max = null) {
    // Check if numeric
    if (!is_numeric($amount)) {
        return false;
    }

    // Check minimum
    if ($amount < $min) {
        return false;
    }

    // Check maximum if provided
    if ($max !== null && $amount > $max) {
        return false;
    }

    return true;
}

/**
 * Validate required fields
 * @param array $fields Associative array of field_name => value
 * @return array Array of missing field names
 */
function validate_required($fields) {
    $missing = [];

    foreach ($fields as $field_name => $value) {
        if (empty(trim($value))) {
            $missing[] = $field_name;
        }
    }

    return $missing;
}

/**
 * Sanitize input data
 * @param mixed $data Data to sanitize
 * @return mixed Sanitized data
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }

    // Trim whitespace and strip tags
    return trim(strip_tags($data));
}

/**
 * Validate phone number
 * @param string $phone Phone number to validate
 * @return bool Validation result
 */
function validate_phone($phone) {
    // Remove all non-numeric characters
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);

    // Check if length is between 10 and 15 digits
    return strlen($clean_phone) >= 10 && strlen($clean_phone) <= 15;
}

/**
 * Validate password strength
 * @param string $password Password to validate
 * @param int $min_length Minimum length
 * @return bool Validation result
 */
function validate_password_strength($password, $min_length = 6) {
    return strlen($password) >= $min_length;
}

