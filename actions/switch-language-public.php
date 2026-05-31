<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * Public Language Switching Handler
 * 
 * Processes language switch requests for guests (non-logged-in users).
 * Stores language preference in session and redirects back to referring page.
 */

// Secure session bootstrap
require_once __DIR__ . '/../includes/session.php';

// Load required functions
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/translation-functions.php';

// Initialize translation
init_translation();

// Get language from query parameter or POST data
$language = isset($_GET['lang']) ? trim($_GET['lang']) : (isset($_POST['lang']) ? trim($_POST['lang']) : null);

// Get redirect URL (default to home if not provided)
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : (isset($_POST['redirect']) ? $_POST['redirect'] : '/');

// Sanitize redirect URL to prevent open redirect
// Only allow relative URLs starting with / and validate no scheme/host present
if (!$redirect || strpos($redirect, '/') !== 0) {
    $redirect = '/';
}

// Validate redirect doesn't contain absolute URL scheme or host
if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $redirect)) {
    // Contains a scheme, reject it
    $redirect = '/';
} else if (strpos($redirect, '//') === 0) {
    // Protocol-relative URL, reject it
    $redirect = '/';
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

// Store language in session for guests
$_SESSION['language'] = $language;

// Reinitialize translation system with new language
init_translation($language);

$_SESSION['success'] = __('Language changed successfully');

// Redirect to original page
header('Location: ' . $redirect);
exit;
