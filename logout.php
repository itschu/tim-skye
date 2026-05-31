<?php
require_once __DIR__ . '/includes/bootstrap.php';
/**
 * Logout Handler
 */

// Secure session bootstrap
require_once ROOT . '/includes/session.php';

// Clear session data
$_SESSION = [];

// Destroy session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'] ?? false, $params['httponly'] ?? true);
}

// Destroy the session
session_unset();
session_destroy();

header('Location: /login?logout=1');
exit;
