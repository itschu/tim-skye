<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * Change password handler
 * Validates current password and updates user password.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validation-functions.php';
require_once __DIR__ . '/../includes/translation-functions.php';

// Initialize translation
init_translation(get_user_language($_SESSION['user_id']));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /user/profile');
    exit;
}

// CSRF check
$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    $_SESSION['error'] = __('Invalid CSRF token');
    header('Location: /user/profile');
    exit;
}

$user_id = $_SESSION['user_id'];
$current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
$confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

// Validate inputs
if (empty($current_password)) {
    $_SESSION['error'] = __('Current password is required');
    header('Location: /user/profile');
    exit;
}

if (empty($new_password)) {
    $_SESSION['error'] = __('New password is required');
    header('Location: /user/profile');
    exit;
}

if (empty($confirm_password)) {
    $_SESSION['error'] = __('Password confirmation is required');
    header('Location: /user/profile');
    exit;
}

// Validate new password strength
if (!validate_password_strength($new_password, 8)) {
    $_SESSION['error'] = __('Password must be at least 8 characters long');
    header('Location: /user/profile');
    exit;
}

// Check if passwords match
if ($new_password !== $confirm_password) {
    $_SESSION['error'] = __('Passwords do not match');
    header('Location: /user/profile');
    exit;
}

// Check if new password is same as current
if ($current_password === $new_password) {
    $_SESSION['error'] = __('New password must be different from current password');
    header('Location: /user/profile');
    exit;
}

try {
    // Fetch current user to verify password
    $user_result = db_query("SELECT password FROM users WHERE id = ?", [$user_id]);
    if (empty($user_result)) {
        $_SESSION['error'] = __('User not found');
        header('Location: /user/profile');
        exit;
    }

    $user = $user_result[0];

    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $_SESSION['error'] = __('Current password is incorrect');
        header('Location: /user/profile');
        exit;
    }

    // Hash new password
    $password_hash = password_hash($new_password, PASSWORD_BCRYPT);

    // Update password
    db_update(
        'users',
        [
            'password' => $password_hash,
            'updated_at' => date('Y-m-d H:i:s')
        ],
        'id = ?',
        [$user_id]
    );

    $_SESSION['success'] = __('Password changed successfully');
    header('Location: /user/profile?success=1');
    exit;
} catch (Exception $e) {
    error_log('[Password Change Error] ' . $e->getMessage(), 3, __DIR__ . '/../logs/db-errors.log');
    $_SESSION['error'] = __('Failed to change password. Please try again later.');
    header('Location: /user/profile');
    exit;
}



