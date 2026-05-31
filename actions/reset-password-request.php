<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * Password Reset Request Handler
 * 
 * Processes password reset request and generates reset token
 * Note: Email sending will be implemented in a subsequent phase
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

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
    $_SESSION['error'] = __('Invalid security token. Please try again.');
    header('Location: /reset-password.php');
    exit;
}

// Sanitize and validate email
$email = sanitize_input($_POST['email'] ?? '');

if (empty($email) || !validate_email($email)) {
    $_SESSION['error'] = __('Please enter a valid email address.');
    header('Location: /reset-password.php');
    exit;
}

// For security, we always show success even if user doesn't exist (prevents user enumeration)
try {
    // Query user by email
    $users = db_query("SELECT id FROM users WHERE email = ?", [$email]);

    if (!empty($users)) {
        $user_id = $users[0]['id'];

        // Generate reset token
        $reset_token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $reset_token);
        $token_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Update user with token and expiry
        db_update('users', [
            'reset_token_hash' => $token_hash,
            'reset_token_expires' => $token_expires
        ], 'id = ?', [$user_id]);

        // Send password reset email (if enabled)
        $email_password_reset = get_setting('email_password_reset', 'yes');

        if ($email_password_reset === 'yes') {
            $site_url = get_site_url();
            $reset_link = $site_url . '/reset-password?token=' . $reset_token;

            $site_logo_url = get_site_logo_url();

            $user_data = db_query("SELECT name FROM users WHERE id = ?", [$user_id]);
            $user_name = !empty($user_data) ? $user_data[0]['name'] : 'User';

            $email_vars = [
                'site_name' => get_setting('site_name', 'Investment Platform'),
                'site_logo' => $site_logo_url,
                'site_url' => $site_url,
                'user_name' => $user_name,
                'reset_link' => $reset_link,
                'expiry_time' => '1 hour',
                'current_year' => date('Y'),
                'support_email' => get_setting('contact_email', 'support@example.com'),

            ];

            try {
                send_template_email($email, 'password-reset', $email_vars, get_user_language($user_id));
            } catch (Exception $e) {
                error_log("Failed to send password reset email: " . $e->getMessage(), 3, __DIR__ . '/../logs/email-errors.log');
            }
        }
    }

    // Always show success message (don't reveal if user exists)
    $_SESSION['message'] = __('If an account exists with that email, you will receive a password reset link.');
    header('Location: /reset-password.php?sent=1');
    exit;
} catch (Exception $e) {
    error_log("Password reset error: " . $e->getMessage());
    $_SESSION['error'] = __('An error occurred. Please try again.');
    header('Location: /reset-password.php');
    exit;
}
