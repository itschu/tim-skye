<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * Admin logo upload handler
 * Requires admin privileges and updates site_logo setting.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/upload-functions.php';
require_once __DIR__ . '/../includes/translation-functions.php';

// Initialize translation
init_translation(get_user_language($_SESSION['user_id']));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/settings');
    exit;
}

// CSRF check
$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    $_SESSION['error'] = __('Invalid CSRF token');
    header('Location: /admin/settings');
    exit;
}

if (!isset($_FILES['logo']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) {
    $_SESSION['error'] = __('No logo file uploaded');
    header('Location: /admin/settings');
    exit;
}

$uploaded = upload_file($_FILES['logo'], 'logo');
if ($uploaded === false) {
    $_SESSION['error'] = __('Failed to upload logo. Please try again.');
    header('Location: /admin/settings');
    exit;
}

// Persist into settings
try {
    if (update_setting('site_logo', $uploaded)) {
        $_SESSION['success'] = __('Logo updated successfully');
    } else {
        $_SESSION['error'] = __('Failed to update logo setting');
    }
} catch (Exception $e) {
    error_log('[Admin Logo Upload] ' . $e->getMessage(), 3, __DIR__ . '/../logs/upload-errors.log');
    $_SESSION['error'] = __('Failed to update logo setting');
}

header('Location: /admin/settings');
exit;



