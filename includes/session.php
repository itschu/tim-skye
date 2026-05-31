<?php
// Prevent direct access
if (!defined('ROOT')) {
    die('Direct access denied');
}

// Load bootstrap if not already loaded
if (!defined('INCLUDES_PATH')) {
    require_once __DIR__ . '/bootstrap.php';
}

// Session bootstrap: configure secure session cookie params before starting session
// Applies sensible defaults and enables stricter session handling.

$env = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';

// Use cookies only for session id
ini_set('session.use_only_cookies', '1');

// HTTP only to mitigate XSS access to the cookie
ini_set('session.cookie_httponly', '1');

// Secure flag only in production
if ($env === 'production') {
    ini_set('session.cookie_secure', '1');
} else {
    // ensure default in non-production does not break local http dev
    ini_set('session.cookie_secure', '0');
}

// Restrict cross-site sending - Lax balances usability and CSRF protection for auth flows
ini_set('session.cookie_samesite', 'Lax');

// Prevent session fixation attacks
ini_set('session.use_strict_mode', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


