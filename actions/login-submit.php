<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * User Login Submission Handler
 * 
 * Processes user login form submission
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
    header('Location: /login');
    exit;
}

// Resolve client IP early for rate-limiting
$client_ip = get_client_ip();

// Sanitize inputs
$email = sanitize_input($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validate required fields
if (empty($email) || empty($password)) {
    $_SESSION['error'] = __('Please enter both email and password.');
    header('Location: /login');
    exit;
}

// Rate-limit check (before DB lookup)
if (is_login_rate_limited($client_ip)) {
    $remaining_seconds = get_login_lockout_remaining($client_ip);
    $remaining_minutes = (int)ceil(max(1, $remaining_seconds) / 60);
    $_SESSION['error'] = sprintf(__('Too many failed login attempts. Please try again in %s minutes.'), $remaining_minutes);
    header('Location: /login');
    exit;
}

// Query user by email
try {
    $users = db_query("SELECT * FROM users WHERE email = ?", [$email]);

    if (empty($users)) {
        // User not found - record failed attempt and use generic message to prevent user enumeration
        record_failed_login($client_ip, $email);
        $_SESSION['error'] = __('Invalid email or password.');
        header('Location: /login');
        exit;
    }

    $user = $users[0];
} catch (Exception $e) {
    error_log("Login error - user query: " . $e->getMessage());
    $_SESSION['error'] = __('An error occurred. Please try again.');
    header('Location: /login');
    exit;
}

// Verify password
if (!password_verify($password, $user['password_hash'])) {
    // Record failed attempt for rate-limiting
    record_failed_login($client_ip, $email);
    $_SESSION['error'] = __('Invalid email or password.');
    header('Location: /login');
    exit;
}

// Check user status
if ($user['status'] === 'banned') {
    $_SESSION['error'] = __('Your account has been suspended.');
    header('Location: /login');
    exit;
}

// Check email verification status if required
$require_email_verification = get_setting('require_email_verification', 'no');
if ($require_email_verification === 'yes' && empty($user['email_verified'])) {
    $_SESSION['error'] = __('Please verify your email address before logging in. Check your inbox for the verification link.');
    header('Location: /login');
    exit;
}

// Successful authentication
try {
    // Clear recorded failed attempts for this IP/email
    clear_login_attempts($client_ip, $email);

    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['last_activity'] = time();

    // Get redirect URL - check POST first (form hidden field), then session
    $intended_url = $_POST['redirect'] ?? $_SESSION['redirect_after_login'] ?? null;

    $redirect_url = get_valid_redirect_url($intended_url, $user['role']);
    unset($_SESSION['redirect_after_login']);

    // Redirect to intended page or default dashboard based on role
    if (empty($redirect_url)) {
        $redirect_url = ($user['role'] === 'admin') ? '/admin/dashboard' : '/user/dashboard';
    }

    header('Location: ' . $redirect_url);
    exit;
} catch (Exception $e) {
    error_log("Login error - session setup: " . $e->getMessage());
    $_SESSION['error'] = __('An error occurred. Please try again.');
    header('Location: /login');
    exit;
}
