<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * Email Verification Handler
 * 
 * Processes email verification token and activates user account
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

// Get token from URL
$token = isset($_GET['token']) ? sanitize_input($_GET['token']) : '';

if (empty($token)) {
    $_SESSION['error'] = __('Invalid verification link.');
    header('Location: /login');
    exit;
}

// Look up user by verification token
try {
    $users = db_query(
        "SELECT * FROM users WHERE email_verification_token = ? AND email_verified = 0",
        [$token]
    );

    if (empty($users)) {
        $_SESSION['error'] = __('Invalid or expired verification link. Please try logging in or contact support.');
        header('Location: /login');
        exit;
    }

    $user = $users[0];

    // Check if token is expired (24 hours)
    $token_sent_at = strtotime($user['email_verification_sent_at']);
    $current_time = time();
    $token_age_hours = ($current_time - $token_sent_at) / 3600;

    if ($token_age_hours > 24) {
        $_SESSION['error'] = __('Verification link has expired. Please request a new one.');
        header('Location: /login');
        exit;
    }

    // Verify the user
    $result = db_update(
        'users',
        [
            'email_verified' => 1,
            'email_verification_token' => null,
            'email_verification_sent_at' => null
        ],
        'id = ?',
        [$user['id']]
    );

    if ($result === false) {
        throw new Exception('Failed to update user verification status');
    }

    $_SESSION['success'] = __('Your email has been verified successfully! You can now log in.');
    header('Location: /login?verified=1');
    exit;
} catch (Exception $e) {
    error_log("Email verification error: " . $e->getMessage());
    $_SESSION['error'] = __('An error occurred while verifying your email. Please try again or contact support.');
    header('Location: /login');
    exit;
}
