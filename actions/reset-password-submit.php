<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * Password Reset Submission Handler
 * 
 * Validates token and sets the new password
 */

// Secure session bootstrap
require_once __DIR__ . '/../includes/session.php';

// Include required files
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validation-functions.php';
require_once __DIR__ . '/../includes/translation-functions.php';

// Initialize translation
init_translation();

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
    $_SESSION['error'] = __('Invalid security token. Please try again.');
    header('Location: /reset-password.php');
    exit;
}

// Sanitize inputs
$token = sanitize_input($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validate inputs
if (empty($token) || empty($password) || empty($confirm_password)) {
    $_SESSION['error'] = __('All fields are required.');
    header('Location: /reset-password.php');
    exit;
}

if (!validate_password_strength($password, 6)) {
    $_SESSION['error'] = __('Password must be at least 6 characters long.');
    header('Location: /reset-password.php?token=' . urlencode($token));
    exit;
}

if ($password !== $confirm_password) {
    $_SESSION['error'] = __('Passwords do not match.');
    header('Location: /reset-password.php?token=' . urlencode($token));
    exit;
}

// Hash token and find user
$token_hash = hash('sha256', $token);

try {
    $users = db_query("SELECT id, reset_token_expires FROM users WHERE reset_token_hash = ?", [$token_hash]);

    if (empty($users)) {
        $_SESSION['error'] = __('Invalid or expired token.');
        header('Location: /reset-password.php');
        exit;
    }

    $user = $users[0];
    $user_id = $user['id'];

    // Check expiry
    if (isset($user['reset_token_expires']) && strtotime($user['reset_token_expires']) < time()) {
        $_SESSION['error'] = __('Reset token has expired. Please request a new link.');
        header('Location: /reset-password.php');
        exit;
    }

    // Hash new password
    $new_hash = password_hash($password, PASSWORD_BCRYPT);

    // Update user record and clear token
    db_update('users', [
        'password_hash' => $new_hash,
        'reset_token_hash' => null,
        'reset_token_expires' => null,
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$user_id]);

    $_SESSION['message'] = __('Password has been reset. You may now log in with your new password.');
    header('Location: /login?reset=1');
    exit;
} catch (Exception $e) {
    error_log("Password reset submit error: " . $e->getMessage());
    $_SESSION['error'] = __('An error occurred. Please try again.');
    header('Location: /reset-password.php');
    exit;
}



