<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * Language Switching Handler
 * 
 * Processes language switch requests from the language dropdown.
 * Updates user's language preference and redirects back to referring page.
 */

// Secure session bootstrap
require_once __DIR__ . '/../includes/session.php';

// Load required functions
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/translation-functions.php';

// Initialize translation
init_translation(get_user_language($_SESSION['user_id']));

// User must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Verify CSRF token
$token = isset($_GET['csrf_token']) || isset($_POST['csrf_token']) ?
    ($_GET['csrf_token'] ?? $_POST['csrf_token']) : '';
if (!verify_csrf($token)) {
    $_SESSION['error'] = __('Invalid security token. Language change cancelled.');
    header('Location: /user/dashboard');
    exit;
}

// Get language from query parameter or POST data
$language = isset($_GET['lang']) ? trim($_GET['lang']) : (isset($_POST['lang']) ? trim($_POST['lang']) : null);

// Get redirect URL (default to dashboard if not provided)
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : (isset($_POST['redirect']) ? $_POST['redirect'] : '/user/dashboard');

// Sanitize redirect URL to prevent open redirect
// Only allow relative URLs starting with / and validate no scheme/host present
if (!$redirect || strpos($redirect, '/') !== 0) {
    $redirect = '/user/dashboard';
}

// Validate redirect doesn't contain absolute URL scheme or host
// Allow query strings and fragments to preserve context
if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $redirect)) {
    // Contains a scheme, reject it
    $redirect = '/user/dashboard';
} else if (strpos($redirect, '//') === 0) {
    // Protocol-relative URL, reject it
    $redirect = '/user/dashboard';
}

// Validate language code
if (!$language || !preg_match('/^[a-z]{2}_[A-Z]{2}$/', $language)) {
    $_SESSION['error'] = __('Invalid language selected');
    header('Location: ' . $redirect);
    exit;
}

// Check if language is available
$available_languages = get_available_languages();
if (!isset($available_languages[$language])) {
    $_SESSION['error'] = __('Language not available');
    header('Location: ' . $redirect);
    exit;
}

// Clear any cached translations
unset($GLOBALS['translations']);
unset($GLOBALS['current_language']);

// Switch language for user
if (switch_language($_SESSION['user_id'], $language)) {
    // Force re-initialization with new language
    init_translation($language);
    $_SESSION['success'] = __('Language changed successfully');
} else {
    $_SESSION['error'] = __('Failed to change language');
}

// Redirect to original page
header('Location: ' . $redirect);
exit;



