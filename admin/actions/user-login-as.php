<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = __('Invalid request method');
    header('Location: /admin/users');
    exit;
}

// Verify CSRF token
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = __('Invalid security token');
    header('Location: /admin/users');
    exit;
}

// Validate user_id
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
if ($user_id <= 0) {
    $_SESSION['error'] = __('Invalid user ID');
    header('Location: /admin/users');
    exit;
}

try {
    // Fetch target user
    $user_query = "SELECT * FROM users WHERE id = ? AND status = 'active'";
    $user_result = db_query($user_query, [$user_id]);

    if (empty($user_result)) {
        $_SESSION['error'] = __('User not found or banned');
        header('Location: /admin/users');
        exit;
    }

    $target_user = $user_result[0];

    // Prevent admin-to-admin switch
    if ($target_user['role'] === 'admin') {
        $_SESSION['error'] = __('Cannot login as another admin');
        header('Location: /admin/users');
        exit;
    }

    // Store original admin session ID
    $_SESSION['admin_original_id'] = $_SESSION['user_id'];

    // Switch session to user
    $_SESSION['user_id'] = $user_id;
    session_regenerate_id(true);
    $_SESSION['last_activity'] = time();

    $_SESSION['success'] = __('Logged in as ') . $target_user['name'];
    header('Location: /user/dashboard');
    exit;
} catch (Exception $e) {
    error_log('Login as user error: ' . $e->getMessage(), 3, '../logs/db-errors.log');
    $_SESSION['error'] = __('An error occurred while switching user');
    header('Location: /admin/users');
    exit;
}
