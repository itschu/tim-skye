<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * Resend Verification Email Handler
 * 
 * Allows users to request a new verification email
 */

// Secure session bootstrap
require_once __DIR__ . '/../includes/session.php';

// Include required files
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validation-functions.php';
require_once __DIR__ . '/../includes/translation-functions.php';
require_once __DIR__ . '/../includes/email-functions.php';

// Initialize translation
init_translation();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login');
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
    $_SESSION['error'] = __('Invalid security token. Please try again.');
    header('Location: /login');
    exit;
}

$email = sanitize_input($_POST['email'] ?? '');

if (empty($email) || !validate_email($email)) {
    $_SESSION['error'] = __('Please enter a valid email address.');
    header('Location: /login');
    exit;
}

// Look up user by email
try {
    $users = db_query(
        "SELECT * FROM users WHERE email = ? AND email_verified = 0",
        [$email]
    );

    // Don't reveal if user exists or not for security
    if (empty($users)) {
        $_SESSION['success'] = __('If an unverified account exists with this email, a new verification link has been sent.');
        header('Location: /login');
        exit;
    }

    $user = $users[0];

    // Check if we recently sent a verification email (rate limiting - 5 minutes)
    if (!empty($user['email_verification_sent_at'])) {
        $last_sent = strtotime($user['email_verification_sent_at']);
        $minutes_since_last = (time() - $last_sent) / 60;

        if ($minutes_since_last < 5) {
            $wait_minutes = ceil(5 - $minutes_since_last);
            $_SESSION['error'] = sprintf(__('Please wait %d minutes before requesting another verification email.'), $wait_minutes);
            header('Location: /login');
            exit;
        }
    }

    // Generate new verification token
    $email_verification_token = bin2hex(random_bytes(32));
    $verification_sent_at = date('Y-m-d H:i:s');

    // Update user with new token
    $result = db_update(
        'users',
        [
            'email_verification_token' => $email_verification_token,
            'email_verification_sent_at' => $verification_sent_at
        ],
        'id = ?',
        [$user['id']]
    );

    if ($result === false) {
        throw new Exception('Failed to update verification token');
    }

    // Send verification email using template (if enabled)
    $email_user_verification = get_setting('email_user_verification', 'yes');

    if ($email_user_verification === 'yes') {
        $site_url = get_site_url();
        $verification_link = $site_url . '/actions/verify-email?token=' . $email_verification_token;

        $site_logo_url = get_site_logo_url();

        $email_vars = [
            'site_name' => get_setting('site_name', 'Investment Platform'),
            'site_logo' => $site_logo_url,
            'site_url' => $site_url,
            'user_name' => $user['name'],
            'verification_link' => $verification_link,
            'current_year' => date('Y'),
            'support_email' => get_setting('contact_email', 'support@example.com'),

        ];

        try {
            send_template_email($email, 'email-verification', $email_vars, get_user_language($user['id']));
        } catch (Exception $e) {
            error_log("Failed to send verification email: " . $e->getMessage(), 3, __DIR__ . '/../logs/email-errors.log');
        }
    }

    $_SESSION['success'] = __('If an unverified account exists with this email, a new verification link has been sent.');
    header('Location: /login');
    exit;
} catch (Exception $e) {
    error_log("Resend verification error: " . $e->getMessage());
    $_SESSION['error'] = __('An error occurred. Please try again.');
    header('Location: /login');
    exit;
}
