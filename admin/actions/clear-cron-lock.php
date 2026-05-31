<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

/**
 * Clear Cron Lock File
 * Removes the lock file that prevents concurrent cron execution
 */

require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/settings');
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
    $_SESSION['error'] = __('Invalid security token. Please try again.');
    header('Location: /admin/settings');
    exit;
}

try {
    $lock_file = __DIR__ . '/../../cron/process-investments.lock';

    // Check if lock file exists
    if (!file_exists($lock_file)) {
        $_SESSION['error'] = __('No cron lock file found.');
        header('Location: /admin/settings');
        exit;
    }

    // Attempt to delete the lock file
    if (unlink($lock_file)) {
        $_SESSION['success'] = __('Cron lock cleared successfully.');
    } else {
        $_SESSION['error'] = __('Failed to clear cron lock. Check file permissions.');
    }
} catch (Exception $e) {
    error_log($e->getMessage(), 3, '../../logs/db-errors.log');
    $_SESSION['error'] = __('An error occurred while clearing the cron lock.');
}

header('Location: /admin/settings');
exit;
