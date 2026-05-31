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
 * User Authentication Guard
 * 
 * This file should be included at the top of all user-facing pages
 * that require authentication. It validates the session and loads
 * the current user data.
 */

// Ensure secure session configuration and start the session
require_once __DIR__ . '/session.php';

// Load required includes if not already loaded
if (!function_exists('db_query')) {
    require_once __DIR__ . '/db.php';
}
if (!function_exists('e')) {
    require_once __DIR__ . '/functions.php';
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Store the intended URL for redirect after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: /login');
    exit;
}

// Get session timeout setting
$session_timeout = $_ENV['SESSION_TIMEOUT'] ?? 1800;

// Check session timeout
if (isset($_SESSION['last_activity'])) {
    $idle_time = time() - $_SESSION['last_activity'];
    if ($idle_time > $session_timeout) {
        // Session has expired - store intended URL before clearing session
        $intended_url = $_SERVER['REQUEST_URI'];
        session_unset();
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
        // Start new session to store redirect URL
        session_start();
        $_SESSION['redirect_after_login'] = $intended_url;
        header('Location: /login?timeout=1');
        exit;
    }
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

// Load current user data
try {
    $users = db_query("SELECT * FROM users WHERE id = ? AND status = 'active'", [$_SESSION['user_id']]);

    if (empty($users)) {
        // User not found or banned
        session_unset();
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
        header('Location: /login');
        exit;
    }

    // Store user data in global for page access
    $GLOBALS['current_user'] = $users[0];
} catch (Exception $e) {
    error_log("Auth error: " . $e->getMessage());
    session_unset();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
    header('Location: /login');
    exit;
}


