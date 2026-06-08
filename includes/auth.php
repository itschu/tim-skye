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
        // User not found or banned — preserve session in case it's a transient read failure
        http_response_code(503);
        die('Account data temporarily unavailable. Please refresh the page in a moment.');
    }

    // Store user data in global for page access
    $GLOBALS['current_user'] = $users[0];
} catch (PDOException $e) {
    error_log("Auth DB error: " . $e->getMessage());

    $sqlState = $e->getCode();
    $errorInfo = $e->errorInfo;
    $driverCode = $errorInfo[1] ?? null;

    // Known transient database error SQLSTATE codes and driver codes
    $transientStates = ['HY000', '40001', '08S01', '08001', '08004'];
    $transientDriverCodes = [2002, 2006, 1205];

    $isTransient = in_array($sqlState, $transientStates, true)
        || in_array($driverCode, $transientDriverCodes, true);

    if ($isTransient) {
        // Preserve the session — user can retry once service recovers
        http_response_code(503);
        die('Service temporarily unavailable. Please refresh the page in a moment.');
    }

    // Non-transient DB errors: fall through to generic Exception handling
    throw $e;
} catch (Exception $e) {
    error_log("Auth error: " . $e->getMessage());
    session_unset();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
    header('Location: /login');
    exit;
}


